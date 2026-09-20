'use strict';
// Real Chrome + rendered production views, synthetic network responses. No live DB/HTTP.
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process'),vm=require('node:vm');
const root=path.resolve(__dirname,'../..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'finance-stock-browser-'));
const views=JSON.parse(execFileSync('/www/server/php/81/bin/php',[path.join(__dirname,'procurement_stock_view_fixture.php')],{maxBuffer:10*1024*1024}));
const chrome=spawn('/usr/bin/google-chrome',['--headless','--no-sandbox','--disable-dev-shm-usage','--disable-background-networking','--no-first-run','--remote-debugging-pipe','--user-data-dir='+path.join(output,'chrome')],{stdio:['ignore','ignore','ignore','pipe','pipe']});
let id=0,buffer='',session,checks=0;const pending=new Map(),errors=[];
chrome.stdio[4].on('data',b=>{buffer+=b.toString();let n;while((n=buffer.indexOf('\0'))>=0){const m=JSON.parse(buffer.slice(0,n));buffer=buffer.slice(n+1);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}}});
const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const key=++id,timer=setTimeout(()=>reject(Error('CDP_TIMEOUT')),20000);pending.set(key,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id:key,method,params,...(sessionId?{sessionId}:{})})+'\0');});
const cdp=(m,p={})=>send(m,p,session);
const evaluate=async expression=>{const r=await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);return r.result.value;};
const check=(condition,message)=>{assert.ok(condition,message);checks++;};
const mock=`window.stockCalls=[];window.saves=[];window.confirms=[];window.confirm=m=>{confirms.push(m);return false;};
window.fetch=async (url,opts={})=>{const p=JSON.parse(opts.body||'{}');if(String(url).includes('stock-preview')){stockCalls.push(p);return {ok:true,json:async()=>({ok:true,data:{rows:(p.lines||[]).map((l,i)=>({line:i+1,name:l.profile_name||'Kopi',uom:'GR',requested:500,division:{qty:1000,state:'KNOWN',message:'saldo'},warehouse:{qty:2000,state:'KNOWN',message:'saldo'},warning:'Stok tujuan masih mencukupi jumlah pengajuan. Konfirmasi kebutuhan tambahan.',needs_confirmation:true})),checked_at:'2026-09-20 10:00:00',has_materials:true,needs_confirmation:true,ready:true,token:'fixture',warehouse_only:p.header?.destination_type==='GUDANG'}})};}saves.push({url,p});return {ok:true,status:200,json:async()=>({ok:true,rows:[]}),text:async()=>'{}'};};`;
(async()=>{try{
 const {targetId}=await send('Target.createTarget',{url:'about:blank'});session=(await send('Target.attachToTarget',{targetId,flatten:true})).sessionId;await cdp('Page.enable');await cdp('Runtime.enable');
 await cdp('Emulation.setDeviceMetricsOverride',{width:1365,height:1000,deviceScaleFactor:1,mobile:false});
 for(const [name,source] of Object.entries(views)){
  errors.length=0;await cdp('Page.navigate',{url:'about:blank'});
  let html=source.replace(/<script\b[^>]*\bsrc="https:\/\/fixture\.invalid\/([^"?]+)(?:\?[^"]*)?"[^>]*><\/script>/g,(_,file)=>'<script>'+fs.readFileSync(path.join(root,file),'utf8')+'</script>');
  for(const match of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(match[1],{filename:name});
  const prelude='<script>'+mock+'</script><script>'+fs.readFileSync(path.join(root,'assets/vendor/js/bootstrap.js'),'utf8')+'</script>';
  html=name==='print'?html.replace('<head>','<head>'+prelude):'<!doctype html><html><head>'+prelude+'</head><body>'+html+'</body></html>';
  const {frameTree}=await cdp('Page.getFrameTree');await cdp('Page.setDocumentContent',{frameId:frameTree.frame.id,html});
  await new Promise(r=>setTimeout(r,1200));check(errors.length===0,name+' script errors: '+errors.join(';'));
  if(name!=='print'){
   const state=await evaluate(`({calls:stockCalls.length,cells:[...document.querySelectorAll('[data-live-stock-line]')].map(n=>n.innerText),text:document.body.innerText})`);
   check(state.calls>0,name+' reads stock on load');check(state.cells.some(s=>s.includes('1.000 GR')&&s.includes('2.000 GR')),name+' current stock actually visible per line');
   if(name==='sr'){await evaluate(`document.getElementById('btnSaveSr').click()`);await new Promise(r=>setTimeout(r,100));check(await evaluate(`confirms.length===1 && !saves.some(s=>String(s.url).endsWith('/store'))`),'SR refuses save when user cancels stock warning');}
   if(name==='po'){
    await evaluate(`document.getElementById('btn-save-po').click()`);await new Promise(r=>setTimeout(r,100));
    if(!(await evaluate('confirms.length'))) await evaluate(`document.getElementById('po-review-confirm').checked=true;document.getElementById('po-review-submit').click()`);
    await new Promise(r=>setTimeout(r,100));
    check(await evaluate(`confirms.length===1 && !saves.some(s=>String(s.url).includes('/order/update/'))`),'PO refuses save when user cancels stock warning: '+await evaluate(`document.getElementById('alert-area').innerText`));
    check(errors.length===0,'PO submit has no JavaScript exception: '+errors.join(';'));
    const large=await evaluate(`(async()=>{const start=stockCalls.length;const reader=ProcurementCurrentStock.create({url:'/stock-preview',csrfHeader:'X-Test',csrf:'fixture',payload:()=>({header:{division_id:7,destination_type:'BAR'},lines:Array.from({length:201},(_,i)=>({profile_name:'Bahan '+i,item_id:1,content_uom_id:1,qty_content_requested:1}))})});const data=await reader.refresh();return {calls:stockCalls.length-start,rows:data.rows.length,last:data.rows.at(-1).line};})()`);
    check(large.calls===3 && large.rows===201 && large.last===201,'large manual PO reads in bounded batches without restricting order length');
   }
  }else{
   check(await evaluate(`document.body.innerText.includes('1.000 GR') && document.body.innerText.includes('Stok sekarang')`),'print current stock quantity visible');
   const pdf=await cdp('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});fs.writeFileSync(path.join(output,'pengajuan-fixture.pdf'),Buffer.from(pdf.data,'base64'));
   const screenshot=await cdp('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,'pengajuan-fixture.png'),Buffer.from(screenshot.data,'base64'));
  }
 }
 console.log(JSON.stringify({status:'PASS',checks,browser:'Chrome',evidence:output,limits:'synthetic network; no real login/database'}));
 }catch(e){console.error(e);process.exitCode=1;}finally{for(const p of pending.values())clearTimeout(p.timer);chrome.kill('SIGTERM');}})();
