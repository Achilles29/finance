<?php
$hasSchema = !empty($has_schema);
$filters = $filters ?? [];
$rows = $rows ?? [];
$summary = $summary ?? [];
$timelineMap = $timeline_map ?? [];
$divisionOptions = $division_options ?? [];
$statusOptions = $status_options ?? [];
$destinationOptions = $destination_options ?? [];
$limit = (int)($limit ?? 50);
$canCreate = !empty($can_create);
$canEdit = !empty($can_edit);
?>

<style>
  .sr-action-btn { width: 34px; height: 34px; border-radius: 10px; padding: 0 !important; display: inline-flex; align-items: center; justify-content: center; }
  .sr-scroll { max-height: 260px; overflow: auto; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><i class="ri-inbox-archive-line text-danger me-1"></i>Store Request</h4>
    <small class="text-muted">Verifikasi, fulfillment gudang, dan generate PO shortage. PO final tetap diproses di menu <strong>Purchase Order</strong>.</small>
  </div>
  <div class="d-flex gap-2">
    <?php if ($canCreate): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#srCreateModal">+ Tambah SR</button>
    <?php endif; ?>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Buka Purchase Order</a>
  </div>
</div>

<?php if (!$hasSchema): ?>
<div class="alert alert-warning">Schema Store Request belum tersedia. Jalankan SQL terbaru procurement.</div>
<?php endif; ?>

<div id="srAlert"></div>

<div class="row g-3 mb-3">
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Total</small><h5 class="mb-0"><?php echo (int)($summary['total'] ?? 0); ?></h5></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Submitted</small><h5 class="mb-0"><?php echo (int)($summary['submitted'] ?? 0); ?></h5></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Approved</small><h5 class="mb-0"><?php echo (int)($summary['approved'] ?? 0); ?></h5></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Fulfilled</small><h5 class="mb-0"><?php echo (int)($summary['fulfilled'] ?? 0); ?></h5></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Rejected</small><h5 class="mb-0"><?php echo (int)($summary['rejected'] ?? 0); ?></h5></div></div></div>
  <div class="col-md-2"><div class="card"><div class="card-body"><small class="text-muted d-block">Void</small><h5 class="mb-0"><?php echo (int)($summary['void'] ?? 0); ?></h5></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?php echo site_url('store-requests'); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No SR / catatan"></div>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $st): ?><option value="<?php echo html_escape($st); ?>" <?php echo ((string)($filters['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo html_escape($st); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Tujuan</label><select name="destination_type" class="form-select"><option value="">Semua</option><?php foreach($destinationOptions as $op): ?><option value="<?php echo html_escape((string)$op['value']); ?>" <?php echo ((string)($filters['destination_type'] ?? '') === (string)$op['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$op['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Limit</label><select name="limit" class="form-select"><?php foreach([25,50,100,200] as $lm): ?><option value="<?php echo $lm; ?>" <?php echo $limit === $lm ? 'selected' : ''; ?>><?php echo $lm; ?></option><?php endforeach; ?></select></div>
      <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a href="<?php echo site_url('store-requests'); ?>" class="btn btn-outline-secondary">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>No SR</th><th>Tanggal</th><th>Divisi</th><th>Tujuan</th><th>Status</th><th class="text-end">Line</th><th class="text-end">Qty Req</th><th class="text-end">Qty Fulfilled</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-3">
            Belum ada Store Request.
            <?php if ($canCreate): ?>
              <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#srCreateModal">Buat SR</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php else: foreach($rows as $r): $st=strtoupper((string)($r['status'] ?? 'DRAFT')); $rid=(int)($r['id'] ?? 0); $tl=(array)($timelineMap[$rid] ?? ['approvals'=>[], 'fulfillments'=>[], 'po_links'=>[]]); ?>
        <tr>
          <td><strong><?php echo html_escape((string)($r['sr_no'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($r['created_by_username'] ?? '-')); ?></div></td>
          <td><?php echo html_escape((string)($r['request_date'] ?? '-')); ?><div class="small text-muted">Need: <?php echo html_escape((string)($r['needed_date'] ?? '-')); ?></div></td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['destination_type'] ?? '-')); ?></td>
          <td><?php echo html_escape($st); ?></td>
          <td class="text-end"><?php echo (int)($r['line_count'] ?? 0); ?></td>
          <td class="text-end"><?php echo ui_num((float)($r['req_content_total'] ?? 0)); ?></td>
          <td class="text-end"><?php echo ui_num((float)($r['fulfilled_content_total'] ?? 0)); ?></td>
          <td>
            <?php if ($canEdit): ?>
            <div class="d-flex flex-wrap gap-1 justify-content-center">
              <?php if ($st === 'SUBMITTED'): ?><button type="button" class="btn btn-sm btn-success sr-action-btn sr-action" data-id="<?php echo $rid; ?>" data-action="APPROVE" title="Approve"><i class="ri-check-line"></i></button><?php endif; ?>
              <?php if ($st === 'SUBMITTED'): ?><button type="button" class="btn btn-sm btn-danger sr-action-btn sr-action" data-id="<?php echo $rid; ?>" data-action="REJECT" title="Reject"><i class="ri-close-line"></i></button><?php endif; ?>
              <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-info text-white sr-action-btn sr-split" data-id="<?php echo $rid; ?>" title="Split"><i class="ri-scissors-cut-line"></i></button><?php endif; ?>
              <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-warning text-dark sr-action-btn sr-fulfill" data-id="<?php echo $rid; ?>" title="Fulfill"><i class="ri-inbox-archive-line"></i></button><?php endif; ?>
              <?php if (in_array($st, ['APPROVED','PARTIAL_FULFILLED'], true)): ?><button type="button" class="btn btn-sm btn-secondary sr-action-btn sr-gpo" data-id="<?php echo $rid; ?>" title="Generate PO Shortage"><i class="ri-file-transfer-line"></i></button><?php endif; ?>
              <?php if (in_array($st, ['DRAFT','SUBMITTED','APPROVED','REJECTED'], true)): ?><button type="button" class="btn btn-sm btn-outline-dark sr-action-btn sr-action" data-id="<?php echo $rid; ?>" data-action="VOID" title="Void"><i class="ri-delete-bin-6-line"></i></button><?php endif; ?>
            </div>
            <?php else: ?><span class="text-muted">Read-only</span><?php endif; ?>
          </td>
        </tr>
        <tr class="bg-light"><td colspan="9">
          <details>
            <summary class="small fw-semibold">Timeline</summary>
            <div class="row mt-2 small">
              <div class="col-md-4"><div class="fw-semibold mb-1">Approval</div><?php if(empty($tl['approvals'])): ?><div class="text-muted">Belum ada.</div><?php else: foreach((array)$tl['approvals'] as $x): ?><div>• <?php echo html_escape((string)($x['created_at'] ?? '-')); ?> | <?php echo html_escape((string)($x['action'] ?? '-')); ?> | <?php echo html_escape((string)($x['actor_username'] ?? '-')); ?></div><?php endforeach; endif; ?></div>
              <div class="col-md-4"><div class="fw-semibold mb-1">Fulfillment</div><?php if(empty($tl['fulfillments'])): ?><div class="text-muted">Belum ada.</div><?php else: foreach((array)$tl['fulfillments'] as $x): ?><div>• <?php echo html_escape((string)($x['fulfillment_no'] ?? '-')); ?> | <?php echo html_escape((string)($x['fulfillment_date'] ?? '-')); ?> | <?php echo html_escape((string)($x['status'] ?? '-')); ?></div><?php endforeach; endif; ?></div>
              <div class="col-md-4"><div class="fw-semibold mb-1">Link PO</div><?php if(empty($tl['po_links'])): ?><div class="text-muted">Belum ada.</div><?php else: foreach((array)$tl['po_links'] as $x): ?><div>• <a href="<?php echo site_url('purchase-orders/detail/' . (int)($x['purchase_order_id'] ?? 0)); ?>"><?php echo html_escape((string)($x['po_no'] ?? ('PO#' . (int)($x['purchase_order_id'] ?? 0)))); ?></a> | <?php echo html_escape((string)($x['status'] ?? '-')); ?></div><?php endforeach; endif; ?></div>
            </div>
          </details>
        </td></tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canCreate): ?>
<div class="modal fade" id="srCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Buat Store Request Manual</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="srCreateAlert"></div>
        <form id="srCreateForm">
          <div class="row g-2 mb-2">
            <div class="col-md-2"><label class="form-label mb-1">Tgl Request</label><input type="date" id="sr_request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="col-md-2"><label class="form-label mb-1">Tgl Butuh</label><input type="date" id="sr_needed_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select id="sr_division_id" class="form-select"><?php foreach($divisionOptions as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)($d['division_name'] ?? $d['name'] ?? ('DIV#'.$d['id']))); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label mb-1">Tujuan</label><select id="sr_destination_type" class="form-select"><?php foreach($destinationOptions as $op): ?><option value="<?php echo html_escape((string)$op['value']); ?>"><?php echo html_escape((string)$op['label']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label mb-1">Catatan</label><input type="text" id="sr_notes" class="form-control" placeholder="Opsional"></div>
          </div>

          <div class="border rounded p-2 mb-2">
            <label class="form-label mb-1">Cari Profile Stok Gudang</label>
            <div class="d-flex gap-2">
              <input type="text" id="sr_profile_q" class="form-control" placeholder="Nama profile / item / material / profile key">
              <button type="button" id="btnSearchProfile" class="btn btn-outline-primary">Cari</button>
            </div>
            <div class="sr-scroll mt-2">
              <table class="table table-sm mb-0">
                <thead><tr><th>Profile</th><th>UOM</th><th class="text-end">Stok</th><th style="width:80px">Aksi</th></tr></thead>
                <tbody id="srProfileResults"><tr><td colspan="4" class="text-muted text-center py-2">Belum ada pencarian.</td></tr></tbody>
              </table>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead><tr><th>Profile</th><th>Jenis</th><th>UOM</th><th class="text-end">Stok Gudang</th><th>Qty Beli Req</th><th>Qty Isi Req</th><th>Aksi</th></tr></thead>
              <tbody id="srLineTableBody"><tr><td colspan="7" class="text-muted text-center py-2">Belum ada line.</td></tr></tbody>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btnCreateSr">Simpan DRAFT</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="srSplitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Preview Split SR (Gudang vs Shortage)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><div id="srSplitModalBody" class="small text-muted">Belum ada data.</div></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>

<script>
(function(){
  'use strict';
  var canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
  var canCreate = <?php echo $canCreate ? 'true' : 'false'; ?>;
  var searchUrl = <?php echo json_encode(site_url('procurement/store-request/profile-search')); ?>;
  var storeUrl = <?php echo json_encode(site_url('procurement/store-request/store')); ?>;
  var actionUrlBase = <?php echo json_encode(site_url('procurement/store-request/action/')); ?>;
  var splitPreviewUrlBase = <?php echo json_encode(site_url('procurement/store-request/split-preview/')); ?>;
  var fulfillUrlBase = <?php echo json_encode(site_url('procurement/store-request/fulfill/')); ?>;
  var generatePoUrlBase = <?php echo json_encode(site_url('procurement/store-request/generate-po/')); ?>;
  var reloadUrl = <?php echo json_encode(site_url('store-requests')); ?>;
  var alertBox = document.getElementById('srAlert');
  var createAlertBox = document.getElementById('srCreateAlert');
  var createLines = [];

  function flash(type, msg){ if(!alertBox) return; alertBox.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+msg+'</div>'; }
  function flashCreate(type, msg){ if(!createAlertBox) return; createAlertBox.innerHTML='<div class="alert alert-'+type+' py-2 mb-2">'+msg+'</div>'; }
  function num(v){ var n=Number(v||0); return Number.isFinite(n)?n:0; }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function fetchJson(url, opts){ return fetch(url, opts).then(function(res){ return res.text().then(function(t){ var d={}; try{d=t?JSON.parse(t):{};}catch(e){d={};} if(!res.ok && !d.ok){ d.ok=false; d.message=d.message||('Request gagal ('+res.status+')'); } return d;}); }); }

  function renderCreateSearch(rows){
    var tb = document.getElementById('srProfileResults'); if(!tb) return;
    if(!rows || !rows.length){ tb.innerHTML='<tr><td colspan="4" class="text-muted text-center py-2">Tidak ada data.</td></tr>'; return; }
    var html='';
    rows.forEach(function(row){
      html += '<tr>'
        + '<td><strong>'+esc(row.profile_name||'-')+'</strong><div class="small text-muted">'+esc(row.profile_key||'')+'</div></td>'
        + '<td>'+esc(row.profile_buy_uom_code||'-')+' -> '+esc(row.profile_content_uom_code||'-')+'</td>'
        + '<td class="text-end">'+num(row.qty_content_balance).toFixed(2)+'</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary sr-pick-profile" data-row="'+esc(JSON.stringify(row))+'">Pilih</button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function renderCreateLines(){
    var tb=document.getElementById('srLineTableBody'); if(!tb) return;
    if(!createLines.length){ tb.innerHTML='<tr><td colspan="7" class="text-muted text-center py-2">Belum ada line.</td></tr>'; return; }
    var html='';
    createLines.forEach(function(line, idx){
      html += '<tr>'
        + '<td><strong>'+esc(line.profile_name||'-')+'</strong><div class="small text-muted">'+esc(line.profile_key||'')+'</div></td>'
        + '<td>'+esc(line.line_kind||'-')+'</td>'
        + '<td>'+esc(line.profile_buy_uom_code||'-')+' -> '+esc(line.profile_content_uom_code||'-')+'</td>'
        + '<td class="text-end">'+num(line.qty_content_balance).toFixed(2)+'</td>'
        + '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm sr-qty-buy" data-idx="'+idx+'" value="'+num(line.qty_buy_requested).toFixed(4)+'"></td>'
        + '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm sr-qty-content" data-idx="'+idx+'" value="'+num(line.qty_content_requested).toFixed(4)+'"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger sr-action-btn sr-remove-line" data-idx="'+idx+'"><i class="ri-delete-bin-line"></i></button></td>'
        + '</tr>';
    });
    tb.innerHTML = html;
  }

  function addCreateLine(row){
    var key=[row.profile_key||'', row.item_id||0, row.material_id||0, row.buy_uom_id||0, row.content_uom_id||0].join('|');
    for(var i=0;i<createLines.length;i++){
      var x=createLines[i];
      var xk=[x.profile_key||'', x.item_id||0, x.material_id||0, x.buy_uom_id||0, x.content_uom_id||0].join('|');
      if(xk===key){ flashCreate('warning','Line dengan profile dan UOM yang sama sudah ada.'); return; }
    }
    var cpb=num(row.profile_content_per_buy); if(cpb<=0){ cpb=1; }
    createLines.push({
      line_kind: row.line_kind || ((num(row.material_id)>0)?'MATERIAL':'ITEM'),
      item_id: num(row.item_id)||null,
      material_id: num(row.material_id)||null,
      profile_key: row.profile_key || '',
      profile_name: row.profile_name || '',
      profile_brand: row.profile_brand || '',
      profile_description: row.profile_description || '',
      profile_expired_date: row.profile_expired_date || '',
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: cpb,
      profile_buy_uom_code: row.profile_buy_uom_code || '',
      profile_content_uom_code: row.profile_content_uom_code || '',
      qty_buy_balance: num(row.qty_buy_balance),
      qty_content_balance: num(row.qty_content_balance),
      qty_buy_requested: 1,
      qty_content_requested: cpb
    });
    renderCreateLines();
  }

  document.addEventListener('click', function(e){
    var pick = e.target.closest('.sr-pick-profile');
    if (pick && canCreate){
      e.preventDefault();
      try { addCreateLine(JSON.parse(pick.getAttribute('data-row') || '{}')); }
      catch (err) { flashCreate('danger', 'Data profile tidak valid.'); }
      return;
    }
    var del = e.target.closest('.sr-remove-line');
    if (del && canCreate){
      e.preventDefault();
      var idxDel = parseInt(del.getAttribute('data-idx') || '-1', 10);
      if (idxDel >= 0){ createLines.splice(idxDel, 1); renderCreateLines(); }
      return;
    }

    if(!canEdit) return;

    var act=e.target.closest('.sr-action');
    if(act){
      e.preventDefault();
      var id=parseInt(act.getAttribute('data-id')||'0',10); var action=String(act.getAttribute('data-action')||'').toUpperCase();
      if(id<=0 || !action) return;
      var old=act.innerHTML; act.disabled=true; act.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(actionUrlBase+id, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:action, notes:''}) })
        .then(function(res){ if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal aksi SR.'); act.disabled=false; act.innerHTML=old; return; } window.location.href=reloadUrl; })
        .catch(function(){ flash('danger','Gagal aksi SR.'); act.disabled=false; act.innerHTML=old; });
      return;
    }

    var splitBtn=e.target.closest('.sr-split');
    if(splitBtn){
      e.preventDefault();
      var sid=parseInt(splitBtn.getAttribute('data-id')||'0',10); if(sid<=0) return;
      var olds=splitBtn.innerHTML; splitBtn.disabled=true; splitBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(splitPreviewUrlBase+sid, {credentials:'same-origin'})
        .then(function(res){
          splitBtn.disabled=false; splitBtn.innerHTML=olds;
          if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal preview split.'); return; }
          var rows=(res.rows||[]); var html=[];
          html.push('<div class="mb-2"><strong>Req:</strong> '+num((res.totals||{}).request_content).toFixed(2)+' | <strong>Dapat Gudang:</strong> '+num((res.totals||{}).fulfillable_content).toFixed(2)+' | <strong>Shortage:</strong> '+num((res.totals||{}).shortage_content).toFixed(2)+'</div>');
          html.push('<div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><th>Line</th><th>Profile</th><th class="text-end">Req</th><th class="text-end">Avail</th><th class="text-end">Fulfill</th><th class="text-end">Shortage</th></tr></thead><tbody>');
          if(!rows.length){ html.push('<tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>'); }
          else { rows.forEach(function(r){ html.push('<tr><td>'+esc(r.line_no)+'</td><td>'+esc(r.profile_name||'-')+'</td><td class="text-end">'+num(r.request_remain_content).toFixed(2)+'</td><td class="text-end">'+num(r.available_content).toFixed(2)+'</td><td class="text-end">'+num(r.fulfillable_content).toFixed(2)+'</td><td class="text-end">'+num(r.shortage_content).toFixed(2)+'</td></tr>'); }); }
          html.push('</tbody></table></div>');
          var body=document.getElementById('srSplitModalBody'); if(body){ body.innerHTML=html.join(''); }
          if(window.bootstrap && document.getElementById('srSplitModal')){ window.bootstrap.Modal.getOrCreateInstance(document.getElementById('srSplitModal')).show(); }
        }).catch(function(){ splitBtn.disabled=false; splitBtn.innerHTML=olds; flash('danger','Gagal preview split.'); });
      return;
    }

    var fulfillBtn=e.target.closest('.sr-fulfill');
    if(fulfillBtn){
      e.preventDefault();
      var fid=parseInt(fulfillBtn.getAttribute('data-id')||'0',10); if(fid<=0) return;
      if(!window.confirm('Post fulfillment dari stok gudang untuk SR ini?')) return;
      var oldf=fulfillBtn.innerHTML; fulfillBtn.disabled=true; fulfillBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(fulfillUrlBase+fid, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({fulfillment_date:new Date().toISOString().slice(0,10), notes:''}) })
        .then(function(res){ if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal fulfill SR.'); fulfillBtn.disabled=false; fulfillBtn.innerHTML=oldf; return; } window.location.href=reloadUrl; })
        .catch(function(){ flash('danger','Gagal fulfill SR.'); fulfillBtn.disabled=false; fulfillBtn.innerHTML=oldf; });
      return;
    }

    var poBtn=e.target.closest('.sr-gpo');
    if(poBtn){
      e.preventDefault();
      var pid=parseInt(poBtn.getAttribute('data-id')||'0',10); if(pid<=0) return;
      if(!window.confirm('Generate draft PO shortage dari SR ini?')) return;
      var oldp=poBtn.innerHTML; poBtn.disabled=true; poBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
      fetchJson(generatePoUrlBase+pid, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify({}) })
        .then(function(res){
          if(!res || !res.ok){ flash('danger',(res&&res.message)?res.message:'Gagal generate draft PO.'); poBtn.disabled=false; poBtn.innerHTML=oldp; return; }
          if(res.data && res.data.redirect_url){ window.location.href=String(res.data.redirect_url); return; }
          window.location.href=reloadUrl;
        })
        .catch(function(){ flash('danger','Gagal generate draft PO.'); poBtn.disabled=false; poBtn.innerHTML=oldp; });
    }
  });

  document.addEventListener('input', function(e){
    if (!canCreate) return;
    var buy = e.target.closest('.sr-qty-buy');
    if (buy){
      var idxB = parseInt(buy.getAttribute('data-idx') || '-1', 10);
      if (idxB >= 0 && createLines[idxB]){
        var cpbB = num(createLines[idxB].profile_content_per_buy); if(cpbB<=0) cpbB=1;
        createLines[idxB].qty_buy_requested = num(buy.value);
        createLines[idxB].qty_content_requested = createLines[idxB].qty_buy_requested * cpbB;
        renderCreateLines();
      }
      return;
    }
    var content = e.target.closest('.sr-qty-content');
    if (content){
      var idxC = parseInt(content.getAttribute('data-idx') || '-1', 10);
      if (idxC >= 0 && createLines[idxC]){
        var cpbC = num(createLines[idxC].profile_content_per_buy); if(cpbC<=0) cpbC=1;
        createLines[idxC].qty_content_requested = num(content.value);
        createLines[idxC].qty_buy_requested = createLines[idxC].qty_content_requested / cpbC;
        renderCreateLines();
      }
    }
  });

  if (canCreate){
    var btnSearch = document.getElementById('btnSearchProfile');
    if (btnSearch){
      btnSearch.addEventListener('click', function(){
        var q = (document.getElementById('sr_profile_q') || {}).value || '';
        fetchJson(searchUrl + '?q=' + encodeURIComponent(String(q).trim()) + '&limit=20', { credentials: 'same-origin' })
          .then(function(res){
            if (!res || res.ok === false){ flashCreate('danger', (res && res.message) ? res.message : 'Gagal memuat profile gudang.'); return; }
            renderCreateSearch(res.rows || []);
          })
          .catch(function(){ flashCreate('danger', 'Gagal memuat profile gudang.'); });
      });
    }

    var btnCreate = document.getElementById('btnCreateSr');
    if (btnCreate){
      btnCreate.addEventListener('click', function(){
        if (!createLines.length){ flashCreate('warning', 'Line Store Request belum ada.'); return; }
        var old = btnCreate.innerHTML;
        btnCreate.disabled = true;
        btnCreate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
        var payload = {
          header: {
            request_date: (document.getElementById('sr_request_date') || {}).value || '',
            needed_date: (document.getElementById('sr_needed_date') || {}).value || '',
            request_division_id: Number((document.getElementById('sr_division_id') || {}).value || 0),
            destination_type: (document.getElementById('sr_destination_type') || {}).value || '',
            notes: (document.getElementById('sr_notes') || {}).value || ''
          },
          lines: createLines
        };
        fetchJson(storeUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).then(function(res){
          if (!res || !res.ok){
            flashCreate('danger', (res && res.message) ? res.message : 'Gagal menyimpan Store Request.');
            btnCreate.disabled = false;
            btnCreate.innerHTML = old;
            return;
          }
          window.location.href = reloadUrl;
        }).catch(function(){
          flashCreate('danger', 'Gagal menyimpan Store Request.');
          btnCreate.disabled = false;
          btnCreate.innerHTML = old;
        });
      });
    }
  }
})();
</script>
