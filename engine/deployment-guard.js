// @loom-file release=0.15.67 revision=4 policy=package-priority
(() => {
  'use strict';
  if (window.LoomDeploymentGuard) return;

  const script = document.currentScript || [...document.scripts].reverse().find(s => /\/engine\/deployment-guard\.js(?:\?|$)/.test(String(s.src || '')));
  const statusUrl = script?.src ? new URL('../api/deployment-status.php', script.src).href : new URL('/api/deployment-status.php', location.origin).href;
  const originalFetch = window.fetch.bind(window);
  let active = false, polling = false, pollTimer = null, lastPayload = null;
  let observedTransaction = '', observedTargetRelease = '', reloading = false;
  let initialProbeDone = false, initialProbePromise = null;

  function sameOrigin(input) {
    try { const raw = input instanceof Request ? input.url : input; return new URL(String(raw), location.href).origin === location.origin; }
    catch { return false; }
  }
  function isStatusRequest(input) {
    try { const raw = input instanceof Request ? input.url : input; const u = new URL(String(raw), location.href); return u.href === statusUrl || /\/api\/deployment-status\.php(?:\?|$)/.test(u.href); }
    catch { return false; }
  }
  function retryDelay(payload, idle=false) {
    if (idle) return document.hidden ? 15000 : 6000;
    const n = Number(payload?.retryAfter || payload?.retry_after || 0);
    return Math.max(1200, Math.min(6000, (n > 0 ? n : 2) * 1000));
  }
  function phaseText(payload={}) {
    const phase = String(payload?.phase || payload?.gatePhase || '').toLowerCase();
    if (phase.includes('verif') || phase.includes('commit')) return 'Release files are committed. LOOM is performing final verification before reopening.';
    if (phase.includes('prepar')) return 'LOOM is preparing the verified deployment transaction.';
    return 'Requests are paused safely while release files are installed.';
  }
  function mountOverlay(payload={}) {
    let host = document.getElementById('loomDeploymentGate');
    if (!host) {
      host = document.createElement('div'); host.id = 'loomDeploymentGate'; host.setAttribute('role','status'); host.setAttribute('aria-live','polite');
      host.innerHTML = '<div class="loom-deploy-card"><div class="loom-deploy-orb">LOOM</div><div><strong>LOOM is updating…</strong><span data-loom-deploy-copy>Requests are paused safely while deployment completes.</span><small data-loom-deploy-detail></small></div></div>';
      const style = document.createElement('style'); style.id = 'loomDeploymentGateStyle';
      style.textContent = '#loomDeploymentGate{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:24px;background:rgba(244,248,252,.88);backdrop-filter:blur(12px);font-family:Inter,system-ui,sans-serif;color:#172231}#loomDeploymentGate .loom-deploy-card{width:min(590px,100%);display:flex;align-items:center;gap:16px;padding:20px 22px;border:1px solid #c9d9e8;border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(23,34,49,.18)}#loomDeploymentGate .loom-deploy-orb{width:52px;height:52px;flex:0 0 52px;display:grid;place-items:center;border-radius:16px;background:#e8f2fc;color:#2878d7;font:900 10px/1 Inter,system-ui;letter-spacing:.12em}#loomDeploymentGate strong{display:block;font-size:16px}#loomDeploymentGate span{display:block;margin-top:4px;color:#66778a;font-size:12px;line-height:1.45}#loomDeploymentGate small{display:block;margin-top:7px;color:#8291a0;font-size:10px}';
      document.head.appendChild(style); (document.body || document.documentElement).appendChild(host);
    }
    const copy = host.querySelector('[data-loom-deploy-copy]'), detail = host.querySelector('[data-loom-deploy-detail]');
    if (copy) copy.textContent = phaseText(payload);
    if (detail) { const target = String(payload?.targetRelease || payload?.target_release || observedTargetRelease || '').trim(), tx = String(payload?.transactionId || payload?.transaction_id || observedTransaction || '').trim(), bits=[]; if(target)bits.push(`Target LOOM ${target}`); if(tx)bits.push(`transaction ${tx.slice(0,8)}`); detail.textContent = bits.join(' · ') || 'Transactional deployment in progress'; }
  }
  function enter(payload={}) {
    lastPayload = payload || {};
    const tx = String(payload?.transactionId || payload?.transaction_id || '').trim(), target = String(payload?.targetRelease || payload?.target_release || '').trim();
    if (tx) observedTransaction = tx; if (target) observedTargetRelease = target;
    if (!active) { active = true; document.documentElement.dataset.loomDeploying = '1'; try { window.dispatchEvent(new CustomEvent('loom:deployment-paused',{detail:lastPayload})); } catch {} }
    mountOverlay(lastPayload); schedulePoll(250);
  }
  function finish(payload={}) {
    if (!active || reloading) return; reloading = true; active = false; delete document.documentElement.dataset.loomDeploying;
    const host=document.getElementById('loomDeploymentGate'), strong=host?.querySelector('strong'), copy=host?.querySelector('[data-loom-deploy-copy]')||host?.querySelector('span');
    const target=String(payload?.canonicalRelease || observedTargetRelease || '').trim(); if(strong)strong.textContent=target?`LOOM ${target} is ready.`:'LOOM update complete.'; if(copy)copy.textContent='The verified release is complete. Reloading this page into one clean runtime…';
    try { sessionStorage.setItem('loom:last-deployment-transaction',JSON.stringify({transactionId:observedTransaction,targetRelease:target,completedAt:Date.now()})); window.dispatchEvent(new CustomEvent('loom:deployment-resumed',{detail:{...payload,transactionId:observedTransaction,targetRelease:target}})); } catch {}
    setTimeout(()=>{ const u=new URL(location.href); if(target)u.searchParams.set('_loom_release',target); u.searchParams.set('_loom_reload',Date.now().toString(36)); location.replace(u.href); },450);
  }
  function syntheticDeploymentResponse() {
    const payload={ok:false,error:'deployment-in-progress',message:'LOOM is updating. Requests are paused until the transactional deployment completes.',deploying:true,phase:lastPayload?.phase||'applying',targetRelease:observedTargetRelease,transactionId:observedTransaction,retryAfter:Number(lastPayload?.retryAfter||2)};
    return new Response(JSON.stringify(payload),{status:503,statusText:'Service Unavailable',headers:{'Content-Type':'application/json','Cache-Control':'no-store','Retry-After':String(payload.retryAfter),'X-LOOM-Deploying':'1','X-LOOM-Target-Release':observedTargetRelease}});
  }
  async function statusFetch(timeoutMs=2000) {
    const ctrl=typeof AbortController!=='undefined'?new AbortController():null, timer=ctrl?setTimeout(()=>ctrl.abort('loom-deployment-status-timeout'),timeoutMs):null;
    try { const r=await originalFetch(`${statusUrl}${statusUrl.includes('?')?'&':'?'}_=${Date.now()}`,{cache:'no-store',headers:{'Cache-Control':'no-cache','Accept':'application/json'},...(ctrl?{signal:ctrl.signal}:{})}); if(!r.ok)return null; return await r.json().catch(()=>null); }
    catch { return null; } finally { if(timer)clearTimeout(timer); }
  }
  function schedulePoll(delay) { if(pollTimer)clearTimeout(pollTimer); pollTimer=setTimeout(poll,Math.max(250,Number(delay||retryDelay(lastPayload,!active)))); }
  async function poll() {
    if(polling||reloading)return; polling=true;
    try { const j=await statusFetch(active?2500:1800); if(j?.deploying)enter(j); else if(j&&active)finish(j); else if(j)lastPayload=j; }
    finally { polling=false; }
    if(!reloading)schedulePoll(retryDelay(lastPayload,!active));
  }
  async function inspect503(response) {
    if(response.status!==503)return null; let deploying=response.headers.get('X-LOOM-Deploying')==='1', payload={};
    try { payload=await response.clone().json(); deploying=deploying||payload?.deploying===true||payload?.error==='deployment-in-progress'; } catch {}
    if(!deploying)return null;
    if(!Object.keys(payload).length)payload={deploying:true,targetRelease:response.headers.get('X-LOOM-Target-Release')||'',retryAfter:Number(response.headers.get('Retry-After')||2)};
    return payload;
  }
  async function guardedFetch(input, init) {
    if(!sameOrigin(input)||isStatusRequest(input))return originalFetch(input,init);
    // Probe in parallel, never in front of application startup. 0.15.66 awaited
    // this probe before every first request, so a slow status endpoint allowed the
    // caller's own AbortController to expire before its real request even began.
    if(!initialProbeDone&&!initialProbePromise)initialProbePromise=probeStatus().finally(()=>{initialProbeDone=true;});
    if(active)return syntheticDeploymentResponse();
    const response=await originalFetch(input,init), payload=await inspect503(response);
    if(payload)enter(payload);
    return response;
  }
  async function probeStatus() { const j=await statusFetch(1800); if(j?.deploying)enter(j); else if(j&&active)finish(j); return j; }

  window.fetch=guardedFetch;
  window.LoomDeploymentGuard=Object.freeze({get active(){return active;},get statusUrl(){return statusUrl;},get transactionId(){return observedTransaction;},enter,probe:probeStatus});
  const start=()=>{if(!initialProbePromise)initialProbePromise=probeStatus().finally(()=>{initialProbeDone=true;});initialProbePromise.finally(()=>schedulePoll(retryDelay(null,true)));};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else queueMicrotask(start);
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'&&!reloading)schedulePoll(50)}); window.addEventListener('focus',()=>{if(!reloading)schedulePoll(50)});
})();
