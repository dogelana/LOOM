// @loom-file release=0.12.08 revision=3 policy=package-priority
(() => {
  'use strict';
  function normalizeProject(project) {
    return String(project || '').trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
  }
  async function jsonFetch(url, options={}) {
    const response = await fetch(url, { cache:'no-store', ...options });
    if (!response.ok) {let detail='';try{const j=await response.clone().json();detail=j?.message||j?.error||''}catch{}const err=new Error(detail||`${response.status} ${response.statusText}`);err.status=response.status;throw err;}
    return response.json();
  }
  function effectiveOrder(module) {
    const reservedId = window.LoomConfig?.moduleOrdering?.reservedFirstActionId || 'core.ui.header-bar';
    const reservedOrder = Number(window.LoomConfig?.moduleOrdering?.reservedFirstOrder ?? 0);
    const minNormal = Number(window.LoomConfig?.moduleOrdering?.minNonReservedOrder ?? 1);
    const fallback = Number(window.LoomConfig?.moduleOrdering?.defaultOrder ?? 50000);
    const id = String(module?.action?.id || '');
    if (id === reservedId) return reservedOrder;
    const parsed = Number.parseInt(String(module?.module?.order ?? ''), 10);
    if (!Number.isFinite(parsed)) return fallback;
    return Math.max(minNormal, parsed);
  }
  function normalizeAndSortRegistry(data) {
    const modules = Array.isArray(data?.modules) ? data.modules : [];
    for (const module of modules) {
      const order = effectiveOrder(module);
      module.order_effective = order;
      module.order_display = String(order).padStart(5, '0');
      module.order_locked = module?.action?.id === (window.LoomConfig?.moduleOrdering?.reservedFirstActionId || 'core.ui.header-bar');
    }
    modules.sort((a,b)=>effectiveOrder(a)-effectiveOrder(b) || String(a?.action?.id||'').localeCompare(String(b?.action?.id||'')));
    data.modules = modules;
    return data;
  }
  class RegistryClient {
    constructor({project, apiBase='../../api', fallbackUrl}) {
      this.project = normalizeProject(project);
      this.apiBase = apiBase.replace(/\/$/, '');
      this.fallbackUrl = fallbackUrl;
      this.lastSource = 'unknown';this.cacheKey=`loom:registry-cache:${this.project}`;this.cachedEtag=null;
    }
    async load() {
      const clientId=window.LoomIdentity?.get?.(this.project)?.clientId||'';
      const liveUrl = `${this.apiBase}/modules.php?project=${encodeURIComponent(this.project)}&clientId=${encodeURIComponent(clientId)}`;
      let cached=null;try{cached=JSON.parse(sessionStorage.getItem(this.cacheKey)||'null')}catch{}
      const headers={};if(cached?.etag)headers['If-None-Match']=cached.etag;
      try {
        const response=await fetch(liveUrl,{cache:'no-cache',headers});
        if(response.status===304&&cached?.data){this.lastSource='validated-session-cache';return normalizeAndSortRegistry(cached.data)}
        if(!response.ok)throw new Error(`${response.status} ${response.statusText}`);
        const data=await response.json();if(!data||!Array.isArray(data.modules))throw new Error('Invalid module registry payload');
        const etag=response.headers.get('ETag')||data.registry_etag||null;try{sessionStorage.setItem(this.cacheKey,JSON.stringify({etag,data,storedAt:Date.now()}))}catch{}
        this.lastSource='live-server-scan';return normalizeAndSortRegistry(data);
      } catch (liveError) {
        if(cached?.data){cached.data.discovery={...(cached.data.discovery||{}),source:'session-cache-fallback',liveError:String(liveError.message||liveError)};this.lastSource='session-cache-fallback';return normalizeAndSortRegistry(cached.data)}
        if (Number(liveError?.status)===403) throw liveError;
        if (!this.fallbackUrl) throw liveError;
        const data = await jsonFetch(`${this.fallbackUrl}${this.fallbackUrl.includes('?')?'&':'?'}_=${Date.now()}`);
        data.discovery = {...(data.discovery||{}), source:'static-fallback', liveError:String(liveError.message||liveError)};
        this.lastSource = 'static-fallback';return normalizeAndSortRegistry(data);
      }
    }
  }
  window.PegboardRegistryClient = RegistryClient;
})();
