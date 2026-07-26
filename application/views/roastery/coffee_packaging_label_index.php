<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$filters = $filters ?? ['q' => '', 'status' => 'ACTIVE'];
$labels = $labels ?? [];
$edit = $edit_row ?? null;
$tableReady = !empty($table_ready);
$isEditing = !empty($edit['id']);
$canSave = $isEditing ? !empty($can_edit) : !empty($can_create);
$imagePath = trim((string)($edit['image_path'] ?? ''));
$imageUrl = $imagePath !== '' ? base_url($imagePath) : '';
$canvasWidth = max(40, (int)($edit['canvas_width_mm'] ?? 90));
$canvasHeight = max(60, (int)($edit['canvas_height_mm'] ?? 140));
$designJson = (string)($edit['design_json'] ?? '{}');
$themePreset = (string)($edit['theme_preset'] ?? 'heritage-cream');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:wght@600;700&family=Jost:wght@400;600;800&family=Libre+Baskerville:wght@400;700&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

<style>
.coffee-label-page{--red:#a70f25;--gold:#d6a84d;--brown:#432115}
.coffee-hero{border:0;border-radius:28px;overflow:hidden;color:#fff;background:radial-gradient(circle at 12% 14%,rgba(255,255,255,.22),transparent 24%),radial-gradient(circle at 90% 20%,rgba(214,168,77,.38),transparent 28%),linear-gradient(135deg,#2b140c,#8e1827 58%,#b66e2f);box-shadow:0 22px 45px rgba(60,28,16,.2)}
.coffee-hero h3{font-family:'Playfair Display',serif;font-weight:900}.hero-kicker{letter-spacing:.23em;text-transform:uppercase;color:#ffe3a1;font-size:.72rem;font-weight:800}
.coffee-chip{border-radius:999px;padding:.45rem .75rem;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);font-size:.8rem;font-weight:700}
.label-workbench{display:grid;grid-template-columns:minmax(370px,1fr) minmax(420px,540px);gap:1.1rem;align-items:start}
.label-panel{border:1px solid rgba(167,15,37,.12);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 16px 40px rgba(70,40,25,.1);overflow:hidden}
.label-panel-head{padding:1rem 1.15rem;border-bottom:1px solid rgba(167,15,37,.12);background:linear-gradient(135deg,#fff,#fff7eb);display:flex;justify-content:space-between;align-items:center;gap:1rem}.label-panel-head h5{margin:0;color:#3b1c14;font-weight:900}
.label-panel-body{padding:1.1rem}.label-form-grid,.label-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.full{grid-column:1/-1}
.label-form-grid label,.label-tools label{font-size:.72rem;font-weight:900;color:#6b3a2a;letter-spacing:.04em;text-transform:uppercase}
.form-control,.form-select{border-radius:13px}.range-line{display:grid;grid-template-columns:86px 1fr 48px;gap:.55rem;align-items:center}.range-line small{font-weight:800;color:#795548}.range-line output{font-size:.75rem;color:var(--red);font-weight:900;text-align:right}.range-line input{accent-color:var(--red)}
.toggle-row{display:flex;flex-wrap:wrap;gap:.45rem}.toggle-row .btn{border-radius:999px;font-weight:800}
.label-preview-card{position:sticky;top:82px}.preview-shell{min-height:640px;display:grid;place-items:center;padding:1.15rem;background:radial-gradient(circle at center,#fff6e6,#efe2d3);border-radius:20px;overflow:auto}
.label-canvas{width:var(--label-preview-w,360px);height:var(--label-preview-h,560px);max-width:100%;position:relative;overflow:hidden;border-radius:18px;background:#f7ecd9;color:#2c1711;box-shadow:0 24px 50px rgba(44,23,17,.28),inset 0 0 0 1px rgba(255,255,255,.45);isolation:isolate}
.label-canvas:before{content:"";position:absolute;inset:14px;border:1px solid rgba(214,168,77,.58);border-radius:13px;z-index:3;pointer-events:none}.label-canvas:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.12),transparent 32%,rgba(67,33,18,.2));z-index:2;pointer-events:none}
.label-bg{position:absolute;inset:0;z-index:1;background:radial-gradient(circle at 30% 20%,#fff8ea,#dfc195)}.label-bg img{width:100%;height:100%;object-fit:cover;object-position:center;filter:saturate(1.05) contrast(1.03)}.label-bg.no-image:before{content:"PNG LABEL ARTWORK";position:absolute;inset:18px;border:1px dashed rgba(95,48,25,.28);border-radius:14px;display:grid;place-items:center;color:rgba(95,48,25,.5);font-weight:900;letter-spacing:.14em}
.label-overlay{position:absolute;inset:0;z-index:4;background:linear-gradient(180deg,rgba(255,248,232,.82),rgba(255,248,232,.35) 35%,rgba(47,21,12,.62));pointer-events:none}.theme-midnight-roast .label-overlay{background:linear-gradient(180deg,rgba(15,10,8,.25),rgba(15,10,8,.1) 36%,rgba(15,10,8,.88))}.theme-clean-white .label-overlay{background:linear-gradient(180deg,rgba(255,255,255,.88),rgba(255,255,255,.18) 50%,rgba(255,255,255,.72))}
.label-watermark{position:absolute;z-index:5;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-12deg);font-family:'Bebas Neue',sans-serif;font-size:84px;color:rgba(255,255,255,.12);letter-spacing:.08em;white-space:nowrap}.label-mark{position:absolute;z-index:6;left:18px;top:18px;padding:6px 10px;border:1px solid rgba(255,255,255,.35);border-radius:999px;font-size:9px;letter-spacing:.18em;font-weight:900;color:#fff6d6;background:rgba(75,43,29,.34);backdrop-filter:blur(5px)}
.label-text{position:absolute;z-index:7;min-height:14px;cursor:pointer;padding:2px 5px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.08;text-shadow:0 1px 0 rgba(255,255,255,.16)}.label-text.active{outline:2px solid rgba(255,193,7,.9);background:rgba(255,193,7,.12)}
.saved-card{border:1px solid rgba(167,15,37,.1);border-radius:18px;padding:.85rem;background:#fff;box-shadow:0 8px 22px rgba(70,40,25,.06);display:flex;gap:.8rem}.saved-thumb{width:54px;height:74px;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#f7e5c6,#d9b66d);flex:none}.saved-thumb img{width:100%;height:100%;object-fit:cover}.saved-title{font-weight:900;color:#511c18}
@media(max-width:1180px){.label-workbench{grid-template-columns:1fr}.label-preview-card{position:static}}@media(max-width:768px){.label-form-grid,.label-tools{grid-template-columns:1fr}}
@media print{body.coffee-label-printing .layout-menu,body.coffee-label-printing .layout-navbar,body.coffee-label-printing .content-footer,body.coffee-label-printing .coffee-label-page>:not(.print-target){display:none!important}body.coffee-label-printing .container-xxl,body.coffee-label-printing .content-wrapper{padding:0!important;margin:0!important}body.coffee-label-printing .print-target{display:grid!important;place-items:center;min-height:100vh;background:#fff}body.coffee-label-printing .label-canvas{width:var(--label-print-w,90mm);height:var(--label-print-h,140mm);box-shadow:none;border-radius:0}}
</style>

<div class="coffee-label-page">
  <div class="card coffee-hero mb-4"><div class="card-body p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div><div class="hero-kicker mb-2">Roastery Label Studio</div><h3 class="mb-2">Label Packaging Kopi</h3><div class="text-white-50">Upload PNG, isi data kopi, atur tipografi, preview, lalu cetak label batch.</div></div>
    <div class="d-flex flex-wrap gap-2"><span class="coffee-chip"><i class="ri ri-image-add-line"></i> PNG artwork</span><span class="coffee-chip"><i class="ri ri-font-size-2"></i> Editable text</span><span class="coffee-chip"><i class="ri ri-printer-line"></i> Print preview</span></div>
  </div></div>

  <?php if (!$tableReady): ?><div class="alert alert-warning">Jalankan SQL <code>sql/2026-07-26a_create_coffee_packaging_labels.sql</code> agar data bisa disimpan.</div><?php endif; ?>

  <div class="label-workbench mb-4">
    <div class="label-panel"><div class="label-panel-head"><div><h5><?php echo $isEditing ? 'Edit Label' : 'Buat Label Baru'; ?></h5><small class="text-muted">Data kopi + setting desain.</small></div><?php if ($isEditing): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('roastery/packaging-labels'); ?>">Baru</a><?php endif; ?></div>
      <div class="label-panel-body">
        <form method="post" action="<?php echo site_url('roastery/packaging-labels/save'); ?>" enctype="multipart/form-data" id="coffeeLabelForm">
          <?php if ($this->config->item('csrf_protection')): ?><input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>"><?php endif; ?>
          <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
          <input type="hidden" name="design_json" id="designJsonInput" value="<?php echo html_escape($designJson); ?>">
          <div class="label-form-grid">
            <div class="full"><label class="form-label">Nama Kopi</label><input class="form-control" name="coffee_name" data-label-field="coffee_name" value="<?php echo html_escape((string)($edit['coffee_name'] ?? '')); ?>" placeholder="NAMUA HOUSE BLEND" required></div>
            <div><label class="form-label">Origin</label><input class="form-control" name="origin" data-label-field="origin" value="<?php echo html_escape((string)($edit['origin'] ?? '')); ?>" placeholder="Kintamani / Gayo"></div>
            <div><label class="form-label">Berat</label><input class="form-control" name="weight_text" data-label-field="weight_text" value="<?php echo html_escape((string)($edit['weight_text'] ?? '200 g')); ?>"></div>
            <div><label class="form-label">Process</label><input class="form-control" name="process_method" data-label-field="process_method" value="<?php echo html_escape((string)($edit['process_method'] ?? '')); ?>" placeholder="Natural / Washed"></div>
            <div><label class="form-label">Roast Level</label><input class="form-control" name="roast_level" data-label-field="roast_level" value="<?php echo html_escape((string)($edit['roast_level'] ?? 'Medium')); ?>"></div>
            <div><label class="form-label">Batch No</label><input class="form-control" name="batch_no" data-label-field="batch_no" value="<?php echo html_escape((string)($edit['batch_no'] ?? '')); ?>"></div>
            <div><label class="form-label">Tanggal Roast</label><input class="form-control" type="date" name="roast_date" data-label-field="roast_date" value="<?php echo html_escape((string)($edit['roast_date'] ?? '')); ?>"></div>
            <div><label class="form-label">Best Before</label><input class="form-control" type="date" name="expiry_date" data-label-field="expiry_date" value="<?php echo html_escape((string)($edit['expiry_date'] ?? '')); ?>"></div>
            <div class="full"><label class="form-label">Tasting Notes</label><textarea class="form-control" name="tasting_notes" data-label-field="tasting_notes" placeholder="Orange peel, brown sugar, floral"><?php echo html_escape((string)($edit['tasting_notes'] ?? '')); ?></textarea></div>
            <div class="full"><label class="form-label">Brew Suggestion</label><input class="form-control" name="brew_suggestion" data-label-field="brew_suggestion" value="<?php echo html_escape((string)($edit['brew_suggestion'] ?? 'Filter / Espresso / Milk Based')); ?>"></div>
            <div class="full"><label class="form-label">Keterangan</label><textarea class="form-control" name="description" data-label-field="description" placeholder="Roasted in small batch by NAMUA Roastery."><?php echo html_escape((string)($edit['description'] ?? '')); ?></textarea></div>
            <div><label class="form-label">Ukuran Label</label><div class="input-group"><input class="form-control" type="number" min="40" max="160" name="canvas_width_mm" id="canvasWidth" value="<?php echo $canvasWidth; ?>"><span class="input-group-text">x</span><input class="form-control" type="number" min="60" max="240" name="canvas_height_mm" id="canvasHeight" value="<?php echo $canvasHeight; ?>"><span class="input-group-text">mm</span></div></div>
            <div><label class="form-label">Tema</label><select class="form-select" name="theme_preset" id="themePreset"><option value="heritage-cream" <?php echo $themePreset==='heritage-cream'?'selected':''; ?>>Heritage Cream</option><option value="midnight-roast" <?php echo $themePreset==='midnight-roast'?'selected':''; ?>>Midnight Roast</option><option value="clean-white" <?php echo $themePreset==='clean-white'?'selected':''; ?>>Clean White</option></select></div>
            <div><label class="form-label">Artwork PNG</label><input class="form-control" type="file" name="label_image" id="labelImageInput" accept="image/png"><small class="text-muted">Upload PNG baru akan mengganti artwork lama.</small></div>
            <div><label class="form-label">Status</label><select class="form-select" name="is_active"><option value="1" <?php echo (int)($edit['is_active'] ?? 1)===1?'selected':''; ?>>Aktif</option><option value="0" <?php echo (int)($edit['is_active'] ?? 1)===0?'selected':''; ?>>Nonaktif</option></select></div>
          </div>
          <hr class="my-4">
          <div class="label-tools">
            <div class="full"><label class="form-label">Teks yang diatur</label><select class="form-select" id="blockSelect"><option value="coffee_name">Nama Kopi</option><option value="origin">Origin</option><option value="process_method">Process</option><option value="roast_level">Roast Level</option><option value="weight_text">Berat</option><option value="tasting_notes">Tasting Notes</option><option value="brew_suggestion">Brew Suggestion</option><option value="batch_no">Batch</option><option value="roast_date">Tanggal Roast</option><option value="expiry_date">Best Before</option><option value="description">Keterangan</option></select></div>
            <div><label class="form-label">Font</label><select class="form-select" id="fontFamily"><option>Playfair Display</option><option>Cormorant Garamond</option><option>Libre Baskerville</option><option>Bebas Neue</option><option>Jost</option></select></div>
            <div><label class="form-label">Warna</label><input class="form-control form-control-color w-100" type="color" id="fontColor" value="#7d1720"></div>
            <div class="full range-line"><small>Ukuran</small><input type="range" id="fontSize" min="7" max="52"><output id="fontSizeOut"></output></div>
            <div class="full range-line"><small>Posisi X</small><input type="range" id="posX" min="0" max="100"><output id="posXOut"></output></div>
            <div class="full range-line"><small>Posisi Y</small><input type="range" id="posY" min="0" max="100"><output id="posYOut"></output></div>
            <div class="full range-line"><small>Lebar</small><input type="range" id="blockWidth" min="10" max="100"><output id="blockWidthOut"></output></div>
            <div class="full range-line"><small>Spasi</small><input type="range" id="letterSpacing" min="0" max="8" step=".1"><output id="letterSpacingOut"></output></div>
            <div class="full toggle-row"><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="bold"><i class="ri ri-bold"></i> Bold</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="italic"><i class="ri ri-italic"></i> Italic</button><button class="btn btn-sm btn-outline-dark" type="button" data-toggle-style="uppercase">Uppercase</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="left">Left</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="center">Center</button><button class="btn btn-sm btn-outline-dark" type="button" data-align="right">Right</button></div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-danger" type="submit" <?php echo (!$tableReady || !$canSave)?'disabled':''; ?>><i class="ri ri-save-3-line me-1"></i>Simpan Label</button><button class="btn btn-outline-dark" type="button" id="printLabelBtn"><i class="ri ri-printer-line me-1"></i>Print Preview</button></div>
        </form>
      </div>
    </div>

    <div class="label-panel label-preview-card print-target"><div class="label-panel-head"><div><h5>Live Preview</h5><small class="text-muted">Klik teks pada label untuk mengatur bloknya.</small></div><span class="badge bg-label-danger">Roastery</span></div>
      <div class="label-panel-body"><div class="preview-shell"><div id="labelCanvas" class="label-canvas theme-<?php echo html_escape($themePreset); ?>" style="--label-preview-w:<?php echo $canvasWidth * 4; ?>px;--label-preview-h:<?php echo $canvasHeight * 4; ?>px;--label-print-w:<?php echo $canvasWidth; ?>mm;--label-print-h:<?php echo $canvasHeight; ?>mm;">
        <div class="label-bg <?php echo $imageUrl===''?'no-image':''; ?>" id="labelBg"><?php if ($imageUrl !== ''): ?><img id="labelImagePreview" src="<?php echo html_escape($imageUrl); ?>" alt="Label artwork"><?php else: ?><img id="labelImagePreview" src="" alt="" style="display:none"><?php endif; ?></div>
        <div class="label-overlay"></div><div class="label-watermark">NAMUA</div><div class="label-mark">NAMUA ROASTERY</div>
        <?php foreach (['coffee_name','origin','process_method','roast_level','weight_text','tasting_notes','brew_suggestion','batch_no','roast_date','expiry_date','description'] as $block): ?><div class="label-text" data-block="<?php echo $block; ?>"></div><?php endforeach; ?>
      </div></div><small class="text-muted d-block mt-3"><i class="ri ri-lightbulb-line me-1"></i>Gunakan artwork PNG sebagai dasar, lalu teks batch tetap bisa diubah cepat.</small></div>
    </div>
  </div>

  <div class="label-panel"><div class="label-panel-head flex-wrap"><div><h5>Draft Label Tersimpan</h5><small class="text-muted">Terbaru paling atas.</small></div><form class="d-flex flex-wrap gap-2" method="get" action="<?php echo site_url('roastery/packaging-labels'); ?>"><input class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Cari nama/origin" style="min-width:220px"><select class="form-select" name="status" style="width:150px"><option value="ACTIVE" <?php echo ($filters['status']??'ACTIVE')==='ACTIVE'?'selected':''; ?>>Aktif</option><option value="INACTIVE" <?php echo ($filters['status']??'')==='INACTIVE'?'selected':''; ?>>Nonaktif</option><option value="ALL" <?php echo ($filters['status']??'')==='ALL'?'selected':''; ?>>Semua</option></select><button class="btn btn-outline-danger" type="submit">Filter</button></form></div>
    <div class="label-panel-body"><?php if (empty($labels)): ?><div class="text-muted py-4 text-center">Belum ada label tersimpan.</div><?php else: ?><div class="row g-3"><?php foreach ($labels as $row): ?><div class="col-md-6 col-xl-4"><div class="saved-card h-100"><div class="saved-thumb"><?php if (!empty($row['image_path'])): ?><img src="<?php echo html_escape(base_url($row['image_path'])); ?>" alt=""><?php endif; ?></div><div class="flex-grow-1 min-w-0"><div class="saved-title text-truncate"><?php echo html_escape((string)$row['coffee_name']); ?></div><div class="small text-muted text-truncate"><?php echo html_escape(trim((string)$row['origin'].' '.(string)$row['weight_text'])); ?></div><div class="d-flex gap-1 mt-2"><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('roastery/packaging-labels?edit='.(int)$row['id']); ?>"><i class="ri ri-edit-line"></i> Edit</a><?php if (!empty($can_delete) && (int)($row['is_active'] ?? 1) === 1): ?><a class="btn btn-sm btn-outline-danger" href="<?php echo site_url('roastery/packaging-labels/delete/'.(int)$row['id']); ?>" onclick="return confirm('Nonaktifkan label ini?')"><i class="ri ri-close-circle-line"></i></a><?php endif; ?></div></div></div></div><?php endforeach; ?></div><?php endif; ?></div>
  </div>
</div>

<script>
(function(){
const initialRaw=<?php echo json_encode($designJson, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const el=id=>document.getElementById(id), fields=[...document.querySelectorAll('[data-label-field]')], by=n=>document.querySelector('[data-label-field="'+n+'"]');
const canvas=el('labelCanvas'), bg=el('labelBg'), img=el('labelImagePreview'), designInput=el('designJsonInput'), blockSelect=el('blockSelect');
const canvasWidthEl=el('canvasWidth'), canvasHeightEl=el('canvasHeight'), themePresetEl=el('themePreset'), imageInput=el('labelImageInput'), formEl=el('coffeeLabelForm'), printBtn=el('printLabelBtn');
const c={fontFamily:el('fontFamily'),fontColor:el('fontColor'),fontSize:el('fontSize'),posX:el('posX'),posY:el('posY'),blockWidth:el('blockWidth'),letterSpacing:el('letterSpacing')};
const o={fontSize:el('fontSizeOut'),posX:el('posXOut'),posY:el('posYOut'),blockWidth:el('blockWidthOut'),letterSpacing:el('letterSpacingOut')};
const defaults={canvas:{theme:'heritage-cream'},blocks:{coffee_name:{x:9,y:18,w:82,size:31,font:'Playfair Display',color:'#7d1720',bold:true,italic:false,uppercase:true,align:'center',letter:1.2},origin:{x:12,y:36,w:76,size:12,font:'Jost',color:'#5a3425',bold:true,uppercase:true,align:'center',letter:1.8},process_method:{x:11,y:43,w:38,size:10,font:'Jost',color:'#fff3d0',bold:true,uppercase:true,align:'left',letter:1.2},roast_level:{x:52,y:43,w:37,size:10,font:'Jost',color:'#fff3d0',bold:true,uppercase:true,align:'right',letter:1.2},weight_text:{x:61,y:76,w:28,size:25,font:'Bebas Neue',color:'#fff6dc',bold:true,uppercase:true,align:'right',letter:1.4},tasting_notes:{x:10,y:55,w:80,size:14,font:'Cormorant Garamond',color:'#fff6e4',bold:true,italic:true,align:'center',letter:.3},brew_suggestion:{x:10,y:68,w:44,size:9,font:'Jost',color:'#ffe0a1',bold:true,uppercase:true,align:'left',letter:1.5},batch_no:{x:10,y:82,w:40,size:8,font:'Jost',color:'#fff4d0',align:'left',letter:1.1},roast_date:{x:10,y:88,w:38,size:8,font:'Jost',color:'#fff4d0',align:'left',letter:.8},expiry_date:{x:52,y:88,w:38,size:8,font:'Jost',color:'#fff4d0',align:'right',letter:.8},description:{x:10,y:93,w:80,size:8,font:'Jost',color:'#fff8e8',align:'center',letter:.1}}};
function clone(x){return JSON.parse(JSON.stringify(x))}function parsed(){try{return JSON.parse(initialRaw||'{}')||{}}catch(e){return{}}}
function merge(a,b){const r=clone(a);if(b.canvas)Object.assign(r.canvas,b.canvas);if(b.blocks)Object.keys(b.blocks).forEach(k=>r.blocks[k]=Object.assign(r.blocks[k]||{},b.blocks[k]));return r}
let state=merge(defaults,parsed()), active=blockSelect.value;
function val(n){const v=(by(n)?.value||'').trim(); if(n==='coffee_name')return v||'NAMUA COFFEE'; if(n==='origin')return v?'ORIGIN '+v:'ORIGIN NUSANTARA'; if(n==='process_method')return v||'PROCESS'; if(n==='roast_level')return v||'ROAST'; if(n==='weight_text')return v||'200 g'; if(n==='tasting_notes')return v||'Chocolate, citrus, brown sugar'; if(n==='brew_suggestion')return v||'Filter / Espresso'; if(n==='batch_no')return v?'BATCH '+v:'BATCH -'; if(n==='roast_date')return v?'ROASTED '+v:'ROASTED -'; if(n==='expiry_date')return v?'BEST BEFORE '+v:'BEST BEFORE -'; if(n==='description')return v||'Roasted in small batch by NAMUA Roastery.'; return v}
function apply(n){const el=document.querySelector('.label-text[data-block="'+n+'"]'); if(!el)return; const s=state.blocks[n]||{}; el.textContent=s.uppercase?val(n).toUpperCase():val(n); el.style.left=(s.x||0)+'%'; el.style.top=(s.y||0)+'%'; el.style.width=(s.w||50)+'%'; el.style.fontSize=(s.size||12)+'px'; el.style.fontFamily='"'+(s.font||'Jost')+'",sans-serif'; el.style.color=s.color||'#2c1711'; el.style.fontWeight=s.bold?'900':'500'; el.style.fontStyle=s.italic?'italic':'normal'; el.style.textAlign=s.align||'left'; el.style.letterSpacing=(s.letter||0)+'px'; el.classList.toggle('active',n===active)}
function refresh(){const w=Math.max(40,Math.min(160,parseInt(canvasWidthEl.value||90,10))),h=Math.max(60,Math.min(240,parseInt(canvasHeightEl.value||140,10))),t=themePresetEl.value||'heritage-cream'; state.canvas.theme=t; canvas.style.setProperty('--label-preview-w',(w*4)+'px'); canvas.style.setProperty('--label-preview-h',(h*4)+'px'); canvas.style.setProperty('--label-print-w',w+'mm'); canvas.style.setProperty('--label-print-h',h+'mm'); canvas.className='label-canvas theme-'+t; Object.keys(state.blocks).forEach(apply); designInput.value=JSON.stringify(state)}
function outs(){o.fontSize.textContent=c.fontSize.value+'px';o.posX.textContent=c.posX.value+'%';o.posY.textContent=c.posY.value+'%';o.blockWidth.textContent=c.blockWidth.value+'%';o.letterSpacing.textContent=c.letterSpacing.value+'px'}
function load(){const s=state.blocks[active]||{};c.fontFamily.value=s.font||'Jost';c.fontColor.value=s.color||'#7d1720';c.fontSize.value=s.size||12;c.posX.value=s.x||0;c.posY.value=s.y||0;c.blockWidth.value=s.w||50;c.letterSpacing.value=s.letter||0;outs();refresh()}
function save(){const s=state.blocks[active]||(state.blocks[active]={});s.font=c.fontFamily.value;s.color=c.fontColor.value;s.size=+c.fontSize.value||12;s.x=+c.posX.value||0;s.y=+c.posY.value||0;s.w=+c.blockWidth.value||50;s.letter=+c.letterSpacing.value||0;refresh()}
fields.forEach(e=>e.addEventListener('input',refresh));[canvasWidthEl,canvasHeightEl,themePresetEl].forEach(e=>e.addEventListener('input',refresh));Object.values(c).forEach(e=>e.addEventListener('input',()=>{outs();save()}));
blockSelect.addEventListener('change',function(){active=this.value;load()});document.querySelectorAll('.label-text').forEach(e=>e.addEventListener('click',function(){active=this.dataset.block;blockSelect.value=active;load()}));
document.querySelectorAll('[data-toggle-style]').forEach(b=>b.addEventListener('click',function(){const s=state.blocks[active]||(state.blocks[active]={});s[this.dataset.toggleStyle]=!s[this.dataset.toggleStyle];refresh()}));document.querySelectorAll('[data-align]').forEach(b=>b.addEventListener('click',function(){(state.blocks[active]||(state.blocks[active]={})).align=this.dataset.align;refresh()}));
imageInput.addEventListener('change',function(){const f=this.files&&this.files[0];if(!f)return;if(f.type!=='image/png'){alert('Artwork harus PNG.');this.value='';return}const r=new FileReader();r.onload=e=>{img.src=e.target.result;img.style.display='';bg.classList.remove('no-image')};r.readAsDataURL(f)});
formEl.addEventListener('submit',refresh);printBtn.addEventListener('click',()=>{refresh();document.body.classList.add('coffee-label-printing');window.print();setTimeout(()=>document.body.classList.remove('coffee-label-printing'),500)});load();
})();
</script>
