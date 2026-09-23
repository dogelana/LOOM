// @loom-file release=0.15.22 revision=1 policy=package-priority
(() => {
  'use strict';
  if (window.LoomDeploymentGuard) return;

  const script = document.currentScript || [...document.scripts].reverse().find(s => /\/engine\/deployment-guard\.js(?:\?|$)/.test(String(s.src || '')));
  const statusUrl = script?.src ? new URL('../api/deployment-status.php', script.src).href : new URL('/api/deployment-status.php', location.origin).href;
  const originalFetch = window.fetch.bind(window);
  const NEVER = new Promise(() => {});
  let active = false;
  let polling = false;
  let pollTimer = null;
  let lastPayload = null;

  function sameOrigin(input) {
    try {
      const raw = input instanceof Request ? input.url : input;
      return new URL(String(raw), location.href).origin === location.origin;
    } catch { return false; }
  }
  function isStatusRequest(input) {
    try {
      const raw = input instanceof Request ? input.url : input;
      const u = new URL(String(raw), location.href);
      return u.href === statusUrl || /\/api\/deployment-status\.php(?:\?|$)/.test(u.href);
    } catch { return false; }
  }
  function retryDelay(payload) {
    const fromPayload = Number(payload?.retryAfter || payload?.retry_after || 0);
    return Math.max(1500, Math.min(10000, (fromPayload > 0 ? fromPayload : 3) * 1000));
  }
  function mountOverlay(payload={}) {
    let host = document.getElementById('loomDeploymentGate');
    if (!host) {
      host = document.createElement('div');
      host.id = 'loomDeploymentGate';
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      host.innerHTML = '<div class="loom-deploy-card"><div class="loom-deploy-orb">LOOM</div><div><strong>LOOM is updating…</strong><span>Requests are paused safely. This page will refresh automatically when deployment finishes.</span><small data-loom-deploy-detail></small></div></div>';
      const style = document.createElement('style');
      style.id = 'loomDeploymentGateStyle';
      style.textContent = '#loomDeploymentGate{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:24px;background:rgba(244,248,252,.82);backdrop-filter:blur(12px);font-family:Inter,system-ui,sans-serif;color:#172231}#loomDeploymentGate .loom-deploy-card{width:min(560px,100%);display:flex;align-items:center;gap:16px;padding:20px 22px;border:1px solid #c9d9e8;border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(23,34,49,.18)}#loomDeploymentGate .loom-deploy-orb{width:52px;height:52px;flex:0 0 52px;display:grid;place-items:center;border-radius:16px;background:#e8f2fc;color:#2878d7;font:900 10px/1 Inter,system-ui;letter-spacing:.12em}#loomDeploymentGate strong{display:block;font-size:16px}#loomDeploymentGate span{display:block;margin-top:4px;color:#66778a;font-size:12px;line-height:1.45}#loomDeploymentGate small{display:block;margin-top:7px;color:#8291a0;font-size:10px}';
      document.head.appendChild(style);
      (document.body || document.documentElement).appendChild(host);
    }
    const detail = host.querySelector('[data-loom-deploy-detail]');
    if (detail) {
      const target = String(payload?.targetRelease || payload?.target_release || '').trim();
      detail.textContent = target ? `Installing LOOM ${target}` : 'Transactional deployment in progress';
    }
  }
  function enter(payload={}) {
    lastPayload = payload || {};
    if (!active) {
      active = true;
      document.documentElement.dataset.loomDeploying = '1';
      window.dispatchEvent(new CustomEvent('loom:deployment-paused', {detail:lastPayload}));
    }
    mountOverlay(lastPayload);
    schedulePoll(250);
  }
  function schedulePoll(delay) {
    if (pollTimer) clearTimeout(pollTimer);
    if (!active) return;
    const hiddenFactor = document.hidden ? 2 : 1;
    pollTimer = setTimeout(poll, Math.max(250, Number(delay || retryDelay(lastPayload))) * hiddenFactor);
  }
  async function poll() {
    if (!active || polling) return;
    polling = true;
    try {
      const r = await originalFetch(`${statusUrl}${statusUrl.includes('?') ? '&' : '?'}_=${Date.now()}`, {cache:'no-store', headers:{'Cache-Control':'no-cache'}});
      const j = await r.json().catch(() => ({}));
      if (r.ok && !j.deploying) {
        const host = document.getElementById('loomDeploymentGate');
        const strong = host?.querySelector('strong');
        const span = host?.querySelector('span');
        if (strong) strong.textContent = 'LOOM update complete.';
        if (span) span.textContent = 'Reloading the clean release…';
        setTimeout(() => location.reload(), 250);
        return;
      }
      if (j?.deploying) lastPayload = j;
    } catch {}
    finally { polling = false; }
    schedulePoll(retryDelay(lastPayload));
  }

  async function guardedFetch(input, init) {
    if (!sameOrigin(input) || isStatusRequest(input)) return originalFetch(input, init);
    if (active) return NEVER;
    const response = await originalFetch(input, init);
    if (response.status === 503) {
      let deploying = response.headers.get('X-LOOM-Deploying') === '1';
      let payload = {};
      if (!deploying) {
        try {
          payload = await response.clone().json();
          deploying = payload?.deploying === true || payload?.error === 'deployment-in-progress';
        } catch {}
      }
      if (deploying) {
        if (!Object.keys(payload).length) {
          payload = {
            deploying:true,
            targetRelease:response.headers.get('X-LOOM-Target-Release') || '',
            retryAfter:Number(response.headers.get('Retry-After') || 3)
          };
        }
        enter(payload);
        return NEVER;
      }
    }
    return response;
  }

  window.fetch = guardedFetch;
  window.LoomDeploymentGuard = Object.freeze({
    get active(){ return active; },
    get statusUrl(){ return statusUrl; },
    enter,
    probe: async () => {
      try {
        const r = await originalFetch(`${statusUrl}${statusUrl.includes('?') ? '&' : '?'}_=${Date.now()}`, {cache:'no-store'});
        const j = await r.json().catch(() => ({}));
        if (j?.deploying) enter(j);
        return j;
      } catch { return null; }
    }
  });
  queueMicrotask(() => window.LoomDeploymentGuard.probe());
})();
