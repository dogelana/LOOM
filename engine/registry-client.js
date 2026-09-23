// @loom-file release=0.15.20 revision=6 policy=package-priority
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
    const modules = (Array.isArray(data?.modules) ? data.modules : []).filter(module=>module?.enabled!==false);
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
      this.lastSource = 'unknown';this.cacheKey=`loom:registry-cache:${window.LoomConfig?.engineVersion||'current'}:${this.project}`;this.cachedEtag=null;this.revalidateInFlight=null;
    }
    _clone(data){try{return structuredClone(data)}catch{try{return JSON.parse(JSON.stringify(data))}catch{return data}}}
    _readCache(){try{return JSON.parse(sessionStorage.getItem(this.cacheKey)||'null')}catch{return null}}
    _writeCache(etag,data){try{sessionStorage.setItem(this.cacheKey,JSON.stringify({etag,data,storedAt:Date.now()}))}catch{}}
    async _fetchLive(liveUrl,cached=null){
      const headers={};if(cached?.etag)headers['If-None-Match']=cached.etag;
      const controller=typeof AbortController!=='undefined'?new AbortController():null;
      const timeout=Math.max(1500,Number(window.LoomConfig?.performance?.registryFetchTimeoutMs||5000));
      const timer=controller?setTimeout(()=>controller.abort('registry-timeout'),timeout):null;
      try{
        const response=await fetch(liveUrl,{cache:'no-cache',headers,...(controller?{signal:controller.signal}:{})});
        if(response.status===304&&cached?.data){cached.storedAt=Date.now();this._writeCache(cached.etag,cached.data);return {data:this._clone(cached.data),source:'validated-session-cache'}}
        if(!response.ok){const err=new Error(`${response.status} ${response.statusText}`);err.status=response.status;throw err}
        const data=await response.json();if(!data||!Array.isArray(data.modules))throw new Error('Invalid module registry payload');
        const etag=response.headers.get('ETag')||data.registry_etag||null;this._writeCache(etag,data);return {data,source:'live-server-scan'};
      }finally{if(timer)clearTimeout(timer)}
    }
    _revalidate(liveUrl,cached){
      if(this.revalidateInFlight)return this.revalidateInFlight;
      this.revalidateInFlight=this._fetchLive(liveUrl,cached).catch(()=>null).finally(()=>{this.revalidateInFlight=null});
      return this.revalidateInFlight;
    }
    async load({startup=false}={}) {
      const clientId=window.LoomIdentity?.get?.(this.project)?.clientId||'';
      const liveUrl = `${this.apiBase}/modules.php?project=${encodeURIComponent(this.project)}&clientId=${encodeURIComponent(clientId)}`;
      const cached=this._readCache();
      const startupMaxAge=Math.max(5000,Number(window.LoomConfig?.performance?.registryStartupCacheMaxAgeMs||120000));
      if(startup&&cached?.data&&Date.now()-Number(cached.storedAt||0)<=startupMaxAge){
        this.lastSource='startup-session-cache';this._revalidate(liveUrl,cached);
        const data=this._clone(cached.data);data.discovery={...(data.discovery||{}),source:'startup-session-cache'};return normalizeAndSortRegistry(data);
      }
      try {
        const live=await this._fetchLive(liveUrl,cached);this.lastSource=live.source;return normalizeAndSortRegistry(live.data);
      } catch (liveError) {
        if(cached?.data){const data=this._clone(cached.data);data.discovery={...(data.discovery||{}),source:'session-cache-fallback',liveError:String(liveError.message||liveError)};this.lastSource='session-cache-fallback';return normalizeAndSortRegistry(data)}
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
