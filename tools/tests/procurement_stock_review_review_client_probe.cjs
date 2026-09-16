'use strict';
// Review-only: reuse the existing synthetic DOM fixture, executing the actual client.
// Never contacts any server. Exit 1 means the open issue is reproduced.
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const smoke=fs.readFileSync(path.join(__dirname,'procurement_stock_review_client_smoke.cjs'),'utf8');
const marker=smoke.indexOf('(async()=>{');
if(marker<0)throw Error('Fixture structure changed; review this probe.');
const context={require,__dirname,console};
vm.runInNewContext(smoke.slice(0,marker)+'\nglobalThis.makeReviewFixture=fixture;',context);
const fixture=context.makeReviewFixture();
// The fake fetch intentionally never resolves. Any application timeout/retry would
// be registered in the fixture's timer and would be executed here immediately.
fixture.timer();
const stalled=fixture.requests.length===1 && fixture.nodes.refresh.disabled && fixture.submit().blocked;
if(stalled){
    console.log('OPEN PR-03 MEDIUM: unresolved preview fetch leaves refresh disabled and verification blocked; no app timeout/recovery timer. Synthetic DOM, no network.');
    process.exitCode=1;
}else console.log('PR-03 not reproduced; review recovery behavior.');
