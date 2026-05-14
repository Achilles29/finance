<?php
$hasSchema = !empty($has_schema);
$filters = $filters ?? [];
$rows = $rows ?? [];
$linksMap = $links_map ?? [];
$divisionOptions = $division_options ?? [];
$limit = (int)($limit ?? 50);
$canCreate = !empty($can_create);
?>

<style>
  .dreq-action-btn { width: 34px; height: 34px; border-radius: 10px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
  .dreq-line-table th, .dreq-line-table td { vertical-align: middle; }
  .dreq-scroll { max-height: 260px; overflow: auto; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><i class="ri-inbox-line text-danger me-1"></i>PO / SR Divisi</h4>
    <small class="text-muted">Divisi mengajukan kebutuhan. Sistem auto-route: stok ada -> SR, stok kurang -> PO.</small>
  </div>
  <a href="<?php echo site_url('store-requests'); ?>" class="btn btn-outline-secondary">Buka Store Request</a>
</div>

<?php if (!$hasSchema): ?>
<div class="alert alert-warning">Schema PO/SR Divisi belum tersedia. Jalankan SQL terbaru modul procurement.</div>
<?php endif; ?>

<?php if ($canCreate && $hasSchema): ?>
<div class="card mb-3">
  <div class="card-body">
    <h6 class="mb-3">Pengajuan PO/SR Baru</h6>
    <div id="dreqAlert"></div>
    <form id="dreqForm">
      <div class="row g-2 mb-2">
        <div class="col-md-2"><label class="form-label mb-1">Tgl Request</label><input type="date" id="dreq_request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="col-md-2"><label class="form-label mb-1">Tgl Butuh</label><input type="date" id="dreq_needed_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="col-md-4"><label class="form-label mb-1">Divisi</label><select id="dreq_division_id" class="form-select"><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label mb-1">Catatan</label><input type="text" id="dreq_notes" class="form-control" placeholder="Opsional"></div>
      </div>

      <div class="border rounded p-2 mb-2">
        <label class="form-label mb-1">Cari Barang (cek stok gudang)</label>
        <div class="d-flex gap-2">
          <input type="text" id="dreq_profile_q" class="form-control" placeholder="Nama profile / item / material">
          <button type="button" id="btnDreqSearch" class="btn btn-outline-primary">Cari</button>
        </div>
        <div class="dreq-scroll mt-2">
          <table class="table table-sm mb-0">
            <thead><tr><th>Profile</th><th>UOM</th><th class="text-end">Stok Isi</th><th style="width:80px">Aksi</th></tr></thead>
            <tbody id="dreqSearchRows"><tr><td colspan="4" class="text-muted text-center py-2">Belum ada pencarian.</td></tr></tbody>
          </table>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-sm dreq-line-table">
          <thead><tr><th>Profile</th><th>Jenis</th><th>UOM</th><th class="text-end">Stok Gudang</th><th>Qty Beli</th><th>Qty Isi</th><th>Aksi</th></tr></thead>
          <tbody id="dreqLines"><tr><td colspan="7" class="text-muted text-center py-2">Belum ada line.</td></tr></tbody>
        </table>
      </div>

      <button type="submit" class="btn btn-primary" id="btnDreqSubmit">Ajukan PO/SR</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body border-bottom">
    <form class="row g-2 align-items-end" method="get" action="<?php echo site_url('procurement/division-po-sr'); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No request/catatan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach(['SUBMITTED','VERIFIED','REJECTED','VOID'] as $st): ?><option value="<?php echo $st; ?>" <?php echo ((string)($filters['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Limit</label><select name="limit" class="form-select"><?php foreach([25,50,100,200] as $lm): ?><option value="<?php echo $lm; ?>" <?php echo $limit === $lm ? 'selected' : ''; ?>><?php echo $lm; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-10 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a href="<?php echo site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary">Reset</a></div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>No Request</th><th>Tanggal</th><th>Divisi</th><th>Status</th><th class="text-end">Line</th><th class="text-end">Qty</th><th>Dokumen</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">Belum ada pengajuan.</td></tr>
        <?php else: foreach($rows as $r): $rid=(int)($r['id'] ?? 0); $links=(array)($linksMap[$rid] ?? []); ?>
          <tr>
            <td><strong><?php echo html_escape((string)($r['request_no'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($r['created_by_username'] ?? '-')); ?></div></td>
            <td><?php echo html_escape((string)($r['request_date'] ?? '-')); ?><div class="small text-muted">Need: <?php echo html_escape((string)($r['needed_date'] ?? '-')); ?></div></td>
            <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($r['status'] ?? '-')); ?></td>
            <td class="text-end"><?php echo (int)($r['line_total'] ?? 0); ?></td>
            <td class="text-end"><?php echo ui_num((float)($r['qty_total'] ?? 0)); ?></td>
            <td>
              <?php if (empty($links)): ?>
                <span class="text-muted">-</span>
              <?php else: foreach($links as $ln): ?>
                <?php if (strtoupper((string)($ln['doc_type'] ?? '')) === 'SR'): ?>
                  <a href="<?php echo site_url('store-requests?q=' . urlencode((string)($ln['doc_no'] ?? ''))); ?>" class="badge bg-info text-dark text-decoration-none me-1">SR: <?php echo html_escape((string)($ln['doc_no'] ?? '')); ?> (<?php echo html_escape((string)($ln['doc_status'] ?? '-')); ?>)</a>
                <?php else: ?>
                  <a href="<?php echo site_url('purchase-orders/detail/' . (int)($ln['doc_id'] ?? 0)); ?>" class="badge bg-secondary text-decoration-none me-1">PO: <?php echo html_escape((string)($ln['doc_no'] ?? '')); ?> (<?php echo html_escape((string)($ln['doc_status'] ?? '-')); ?>)</a>
                <?php endif; ?>
              <?php endforeach; endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  'use strict';
  var hasSchema = <?php echo $hasSchema ? 'true' : 'false'; ?>;
  var canCreate = <?php echo $canCreate ? 'true' : 'false'; ?>;
  var searchUrl = <?php echo json_encode(site_url('procurement/store-request/profile-search')); ?>;
  var storeUrl = <?php echo json_encode(site_url('procurement/division-po-sr/store')); ?>;
  var reloadUrl = <?php echo json_encode(site_url('procurement/division-po-sr')); ?>;
  var lines = [];

  function esc(s){ return String(s || '').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function num(v){ var n = Number(v || 0); return Number.isFinite(n) ? n : 0; }
  function flash(type, msg){ var el=document.getElementById('dreqAlert'); if(!el) return; el.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+msg+'</div>'; }

  function fetchJson(url, opts){
    return fetch(url, opts).then(function(res){ return res.text().then(function(t){ var d={}; try{d=t?JSON.parse(t):{};}catch(e){d={};} if(!res.ok && !d.ok){ d.ok=false; d.message=d.message||('Request gagal ('+res.status+')'); } return d;}); });
  }

  function renderSearch(rows){
    var tb=document.getElementById('dreqSearchRows'); if(!tb) return;
    if(!rows || !rows.length){ tb.innerHTML='<tr><td colspan="4" class="text-muted text-center py-2">Tidak ada data.</td></tr>'; return; }
    var html='';
    rows.forEach(function(r){
      html += '<tr>'
        + '<td><strong>'+esc(r.profile_name||'-')+'</strong><div class="small text-muted">'+esc(r.profile_key||'')+'</div></td>'
        + '<td>'+esc(r.profile_buy_uom_code||'-')+' -> '+esc(r.profile_content_uom_code||'-')+'</td>'
        + '<td class="text-end">'+num(r.qty_content_balance).toFixed(2)+'</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary dreq-pick" data-row="'+esc(JSON.stringify(r))+'">Pilih</button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function renderLines(){
    var tb=document.getElementById('dreqLines'); if(!tb) return;
    if(!lines.length){ tb.innerHTML='<tr><td colspan="7" class="text-muted text-center py-2">Belum ada line.</td></tr>'; return; }
    var html='';
    lines.forEach(function(r, idx){
      html += '<tr>'
      + '<td><strong>'+esc(r.profile_name||'-')+'</strong><div class="small text-muted">'+esc(r.profile_key||'')+'</div></td>'
      + '<td>'+esc(r.line_kind||'-')+'</td>'
      + '<td>'+esc(r.profile_buy_uom_code||'-')+' -> '+esc(r.profile_content_uom_code||'-')+'</td>'
      + '<td class="text-end">'+num(r.qty_content_balance).toFixed(2)+'</td>'
      + '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm dreq-buy" data-idx="'+idx+'" value="'+num(r.qty_buy_requested).toFixed(4)+'"></td>'
      + '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm dreq-content" data-idx="'+idx+'" value="'+num(r.qty_content_requested).toFixed(4)+'"></td>'
      + '<td><button type="button" class="btn btn-sm btn-outline-danger dreq-action-btn dreq-del" data-idx="'+idx+'"><i class="ri-delete-bin-line"></i></button></td>'
      + '</tr>';
    });
    tb.innerHTML = html;
  }

  function addLine(row){
    var key=[row.profile_key||'', row.item_id||0, row.material_id||0, row.buy_uom_id||0, row.content_uom_id||0].join('|');
    for(var i=0;i<lines.length;i++){ var x=lines[i]; var xk=[x.profile_key||'', x.item_id||0, x.material_id||0, x.buy_uom_id||0, x.content_uom_id||0].join('|'); if(xk===key){ flash('warning','Profile ini sudah dipilih.'); return; } }
    var cpb = num(row.profile_content_per_buy); if(cpb<=0) cpb=1;
    lines.push({
      line_kind: row.line_kind || ((num(row.material_id)>0)?'MATERIAL':'ITEM'),
      item_id: num(row.item_id)||null,
      material_id: num(row.material_id)||null,
      profile_key: row.profile_key||'',
      profile_name: row.profile_name||'',
      profile_brand: row.profile_brand||'',
      profile_description: row.profile_description||'',
      profile_expired_date: row.profile_expired_date||'',
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: cpb,
      profile_buy_uom_code: row.profile_buy_uom_code||'',
      profile_content_uom_code: row.profile_content_uom_code||'',
      qty_buy_balance: num(row.qty_buy_balance),
      qty_content_balance: num(row.qty_content_balance),
      qty_buy_requested: 1,
      qty_content_requested: cpb
    });
    renderLines();
  }

  var btnSearch=document.getElementById('btnDreqSearch');
  if(btnSearch && hasSchema && canCreate){
    btnSearch.addEventListener('click', function(){
      var q=(document.getElementById('dreq_profile_q')||{}).value||'';
      fetchJson(searchUrl+'?q='+encodeURIComponent(String(q).trim())+'&limit=20', {credentials:'same-origin'})
        .then(function(res){ if(!res || res.ok===false){ flash('danger', (res&&res.message)?res.message:'Gagal memuat barang.'); return; } renderSearch(res.rows||[]); })
        .catch(function(){ flash('danger','Gagal memuat barang.'); });
    });
  }

  document.addEventListener('click', function(e){
    var pick=e.target.closest('.dreq-pick');
    if(pick){ e.preventDefault(); try{ addLine(JSON.parse(pick.getAttribute('data-row')||'{}')); }catch(err){ flash('danger','Data barang tidak valid.'); } return; }
    var del=e.target.closest('.dreq-del');
    if(del){ e.preventDefault(); var idx=parseInt(del.getAttribute('data-idx')||'-1',10); if(idx>=0){ lines.splice(idx,1); renderLines(); } return; }
  });

  document.addEventListener('input', function(e){
    var buy=e.target.closest('.dreq-buy');
    if(buy){ var idx=parseInt(buy.getAttribute('data-idx')||'-1',10); if(idx>=0&&lines[idx]){ var cpb=num(lines[idx].profile_content_per_buy); if(cpb<=0) cpb=1; lines[idx].qty_buy_requested=num(buy.value); lines[idx].qty_content_requested=lines[idx].qty_buy_requested*cpb; renderLines(); } return; }
    var content=e.target.closest('.dreq-content');
    if(content){ var idx2=parseInt(content.getAttribute('data-idx')||'-1',10); if(idx2>=0&&lines[idx2]){ var cpb2=num(lines[idx2].profile_content_per_buy); if(cpb2<=0) cpb2=1; lines[idx2].qty_content_requested=num(content.value); lines[idx2].qty_buy_requested=lines[idx2].qty_content_requested/cpb2; renderLines(); } }
  });

  var form=document.getElementById('dreqForm');
  if(form && hasSchema && canCreate){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      if(!lines.length){ flash('warning','Line pengajuan belum ada.'); return; }
      var btn=document.getElementById('btnDreqSubmit'); var old=btn?btn.innerHTML:'';
      if(btn){ btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...'; }
      var payload={
        header:{
          request_date:(document.getElementById('dreq_request_date')||{}).value||'',
          needed_date:(document.getElementById('dreq_needed_date')||{}).value||'',
          division_id:Number((document.getElementById('dreq_division_id')||{}).value||0),
          notes:(document.getElementById('dreq_notes')||{}).value||''
        },
        lines: lines
      };
      fetchJson(storeUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) })
        .then(function(res){
          if(!res || !res.ok){ flash('danger', (res&&res.message)?res.message:'Gagal menyimpan.'); if(btn){btn.disabled=false; btn.innerHTML=old;} return; }
          window.location.href = reloadUrl;
        }).catch(function(){ flash('danger','Gagal menyimpan.'); if(btn){btn.disabled=false; btn.innerHTML=old;} });
    });
  }
})();
</script>
