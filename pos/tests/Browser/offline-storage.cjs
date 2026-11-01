const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const ts = require('../../../backend/node_modules/typescript');
const http = require('node:http');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const directory = 'temp/phase2883-storage';
fs.mkdirSync(directory, { recursive: true });
const runDirectory = directory + '/' + Date.now();
fs.mkdirSync(runDirectory);
const compiled = ['EncryptedQueue','TrustedClock'].map(name=>ts.transpileModule(fs.readFileSync('pos/resources/offline/'+name+'.ts','utf8'), {compilerOptions:{target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.CommonJS}}).outputText).join('\n');
fs.writeFileSync(directory+'/vault.js','var OfflineQueue = (() => {const exports = {};'+compiled+';return exports;})();');
const server = http.createServer((req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  if (req.url === '/vault.js') { res.setHeader('Content-Type','text/javascript'); res.end(fs.readFileSync(directory+'/vault.js')); }
  else if (req.url === '/') { res.setHeader('Content-Type','text/html'); res.end('<!doctype html><meta charset="utf-8"><title>Synthetic encrypted queue fixture</title><script src="/vault.js"></script>'); }
  else { res.writeHead(404); res.end(); }
});
const scope = {instanceId:'33333333-3333-4333-8333-333333333333', branchId:'44444444-4444-4444-8444-444444444444', staffId:'55555555-5555-4555-8555-555555555555', terminalId:'66666666-6666-4666-8666-666666666666', deviceId:'77777777-7777-4777-8777-777777777777'};
(async () => {
  await new Promise((resolve, reject) => {server.once('error',reject);server.listen(8145,'127.0.0.1',resolve);});
  let context;
  const report = {scope:'Actual Chrome IndexedDB/WebCrypto storage primitive only; no sale permits/PWA shell/server financial sync', cases:[]};
  const launch = async () => chromium.launchPersistentContext(runDirectory+'/chrome-profile', {executablePath:'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless:true});
  try {
    context = await launch();
    let page = await context.newPage();
    await page.goto('http://127.0.0.1:8145/');
    const first = await page.evaluate(async scope => {
      const vault = await OfflineQueue.EncryptedQueue.open(scope);
      await vault.initialize('synthetic unlock phrase', {maxTransactions:2,maxAmountCents:'10000'});
      const storage = await OfflineQueue.EncryptedQueue.storage();
      const entry = await vault.enqueue({kind:'sale.draft',amountCents:'5000',data:{note:'synthetic confidential evidence'}});
      vault.lock();
      let locked = false; try { await vault.entries(); } catch { locked = true; }
      await vault.unlock('synthetic unlock phrase');
      const recovered = await vault.entries();
      const opening = indexedDB.open('company-pos-offline-v1');
      const db = await new Promise(resolve=>{opening.onsuccess=()=>resolve(opening.result);});
      const dump = await Promise.all(['vaults','records'].map(name=>new Promise(resolve=>{const r=db.transaction(name).objectStore(name).getAll();r.onsuccess=()=>resolve(r.result);} )));
      db.close(); vault.close();
      return {id:entry.id,locked,recovered:recovered.length,persistent:storage.persistent,headroomKnown:storage.freeBytes>0,dump:JSON.stringify(dump)};
    },scope);
    assert.equal(first.locked,true); assert.equal(first.recovered,1); assert.equal(first.headroomKnown,true);
    assert.equal(first.dump.includes('synthetic confidential evidence'),false);
    assert.equal(first.dump.includes('synthetic unlock phrase'),false);
    report.cases.push({name:'durable encrypted write and lock',result:'PASS',persistent:first.persistent});
    await context.close();
    context = await launch();
    page = await context.newPage(); await page.goto('http://127.0.0.1:8145/');
    const restarted = await page.evaluate(async ({scope,id}) => {
      const vault = await OfflineQueue.EncryptedQueue.open(scope);
      let wrong = false;try{await vault.unlock('incorrect unlock phrase');}catch{wrong=true;}
      await vault.unlock('synthetic unlock phrase');
      const recovered = await vault.entries();
      const second = await OfflineQueue.EncryptedQueue.open(scope); await second.unlock('synthetic unlock phrase');
      const results = await Promise.allSettled([vault.enqueue({kind:'sale.draft',amountCents:'4000',data:{}}),second.enqueue({kind:'sale.draft',amountCents:'4000',data:{}})]);
      const rows = await vault.entries();
      const other = await OfflineQueue.EncryptedQueue.open({...scope,branchId:'88888888-8888-4888-8888-888888888888'});
      let isolated = false;try{await other.unlock('synthetic unlock phrase');}catch{isolated=true;}
      const result={wrong,isolated,idPreserved:recovered[0].id===id,count:rows.length,fulfilled:results.filter(r=>r.status==='fulfilled').length};
      const opening = indexedDB.open('company-pos-offline-v1');
      const db = await new Promise(resolve=>{opening.onsuccess=()=>resolve(opening.result);});
      await new Promise((resolve,reject)=>{const tx=db.transaction('records','readwrite');tx.objectStore('records').delete(rows[0].id);tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);});
      let missingDetected=false;try{await vault.entries();}catch{missingDetected=true;}
      let blockedAfterLoss=false;try{await vault.enqueue({kind:'sale.draft',amountCents:'1',data:{}});}catch{blockedAfterLoss=true;}
      result.missingDetected=missingDetected;result.blockedAfterLoss=blockedAfterLoss;
      db.close();
      vault.close();second.close();other.close();return result;
    },{scope,id:first.id});
    assert.equal(restarted.wrong,true);assert.equal(restarted.isolated,true);assert.equal(restarted.idPreserved,true);assert.equal(restarted.count,2);assert.equal(restarted.fulfilled,1);
    assert.equal(restarted.missingDetected,true);assert.equal(restarted.blockedAfterLoss,true);
    report.cases.push({name:'browser restart, incorrect passphrase, scope isolation and atomic cross-tab cap',result:'PASS'});
    const clock = await page.evaluate(() => {
      const guard = new OfflineQueue.TrustedClock();
      const initiallyBlocked = !guard.evidence().eligible;
      const wallNow = Date.now;
      const now = wallNow();
      guard.begin({id:crypto.randomUUID(),version:1,serverNow:new Date(now).toISOString(),expiresAt:new Date(now+900000).toISOString()},performance.now());
      const eligible = guard.evidence().eligible;
      Date.now = () => wallNow()-60000;
      const changed = guard.evidence();
      Date.now = wallNow;
      const remainsBlocked = !guard.evidence().eligible;
      guard.close();
      const restarted = new OfflineQueue.TrustedClock();
      const restartBlocked = !restarted.evidence().eligible;
      restarted.close();
      return {initiallyBlocked,eligible,changed:changed.reason,remainsBlocked,restartBlocked};
    });
    assert.equal(clock.initiallyBlocked,true);assert.equal(clock.eligible,true);assert.equal(clock.changed,'clock_changed');assert.equal(clock.remainsBlocked,true);assert.equal(clock.restartBlocked,true);
    report.cases.push({name:'clock rollback freezes authority; fresh online sample required after new context',result:'PASS'});
    report.result='PASS';
  } catch(e) {report.result='FAIL';report.error=e.message;console.error(e.message);process.exitCode=1;}
  finally {if(context)await context.close();await new Promise(resolve=>server.close(resolve));report.serverStopped=true;fs.writeFileSync(directory+'/report.json',JSON.stringify(report,null,2));}
})();
