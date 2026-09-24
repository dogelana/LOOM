// @loom-file release=0.15.34 revision=13 policy=package-priority
(() => {
  'use strict';
  const CFG=window.LoomConfig||window.PegboardEngineConfig;
  const RUNTIME_SCRIPT_URL=document.currentScript?.src||new URL('engine/action-runtime.js',document.baseURI).href;
  const LOOM_ROOT_URL=new URL('../',RUNTIME_SCRIPT_URL).href;
  class LoomActionRuntime {
    constructor({project,mountRoot,apiBase='../../api',fallbackRegistry,userId=null,userLabel=null}){
      this.project=project;this.mountRoot=mountRoot;this.apiBase=apiBase;
      this.registry=new window.PegboardRegistryClient({project,apiBase,fallbackUrl:fallbackRegistry});
      if(userId!=null)window.LOOM_USER_ID=userId;if(userLabel!=null)window.LOOM_USER_LABEL=userLabel;
      this.identity=window.LoomIdentity.get(project);this.bus=new window.LoomEventBus(project,this.identity);
      this.modules=new Map();this.activeActions=new Map();this.userActionDescriptors=new Map();this.activeUserActions=new Map();this.discoveredDescriptors=[];this.bootstrapLoaderId=null;this.bootstrapLoadState=null;this.running=false;this.pollTimer=null;this.heartbeatTimer=null;this.heartbeatRetryTimer=null;this.heartbeatInFlight=false;this.lastHeartbeatSentAt=0;this.lastHeartbeatAckAt=0;this.lastHeartbeatErrorAt=0;this.pageHideCloseSent=false;this.pageHideAt=0;this.logQueue=[];this.logDraining=false;this.logDrainPromise=null;this.modulePreloads=new Set();this.moduleLayoutState={collapsed:{}};this.moduleFrameStyle=null;this.mobileMedia=window.matchMedia?.('(max-width: 767px)')||null;this.responsiveTimer=null;this._onResponsiveModuleVisibilityChange=()=>{clearTimeout(this.responsiveTimer);this.responsiveTimer=setTimeout(()=>{if(this.running)this.refresh(false).catch(err=>this._emitEngineError(err))},90)};
      this.runtimeId=crypto?.randomUUID?.()||`${Date.now()}_${Math.random().toString(36).slice(2)}`;
      this.coreSteps=new Map();this.closed=false;this.closeToken=null;this.correlationId=`corr_${this.runtimeId}`;this.lastEventId=null;this.stopInteractionCapture=null;
      this._onPageHide=e=>this._handlePageHide(e);
      this._onPageShow=e=>this._ensureSessionLiveness(e?.persisted?'pageshow-bfcache':'pageshow',true);
      this._onPresenceActivity=()=>this._activityHeartbeat('user-activity');
      this._onVisibilityChange=()=>{const visible=document.visibilityState==='visible';this._ensureSessionLiveness(visible?'visibility-visible':'visibility-hidden',visible)};
      this._onFocus=()=>this._ensureSessionLiveness('window-focus',true);
      this._onOnline=()=>this._ensureSessionLiveness('network-online',true);
      this._presenceActivityEvents=['pointerdown','keydown','input','change','touchstart'];
    }
    _cacheBustUrl(url,version=null){
      if(!url||CFG?.cacheBust?.enabled===false)return url;
      const token=String(version||CFG?.cacheBust?.staticVersion||CFG?.engineVersion||Date.now());
      const join=String(url).includes('?')?'&':'?';
      return `${url}${join}v=${encodeURIComponent(token)}`;
    }
    _resolveRuntimeUrl(url){
      if(!url)return url;
      const raw=String(url).trim();
      try{
        if(/^[a-z][a-z0-9+.-]*:/i.test(raw)||raw.startsWith('//'))return new URL(raw,location.href).href;
        if(raw.startsWith('/'))return new URL(raw,location.origin).href;
        // Static fallback registries use paths such as ./projects/<slug>/...
        // Those paths are LOOM-root-relative, not relative to /engine/action-runtime.js.
        return new URL(raw.replace(/^\.\//,''),LOOM_ROOT_URL).href;
      }catch{
        return raw;
      }
    }
    async start(){
      if(this.running)return;this.running=true;this.closed=false;
      await this._log({type:'session.start',runtimeId:this.runtimeId,meta:this.identity.meta,userLabel:this.identity.userLabel});
      this._loadModuleLayoutState();this._bindResponsiveModuleVisibility();
      this._emitAction('session.presence','active',{kind:'system',behavior:'stateful',name:'Session Presence'});
      this._emitAction('core.load','active',{kind:'system',behavior:'stateful',name:'Load Core'});
      await this._coreStep('discover-project',async()=>{});
      await this.refresh(true);
      await this._coreStep('ready',async()=>{});
      if(window.LoomInteractionCapture)this.stopInteractionCapture=window.LoomInteractionCapture.start({project:this.project,identity:this.identity,runtimeId:this.runtimeId,apiBase:this.apiBase});
      this._startHeartbeat();this._bindPresenceActivity();this._schedulePoll();
      addEventListener('pagehide',this._onPageHide);addEventListener('pageshow',this._onPageShow);addEventListener('focus',this._onFocus,{passive:true});
    }
    async stop(){return this.close('runtime-stop')}
    async close(reason='closed'){
      if(this.closed)return;this.closed=true;this.running=false;
      clearTimeout(this.pollTimer);clearTimeout(this.responsiveTimer);clearTimeout(this.heartbeatTimer);clearTimeout(this.heartbeatRetryTimer);this._unbindPresenceActivity();this._unbindResponsiveModuleVisibility();removeEventListener('pagehide',this._onPageHide);removeEventListener('pageshow',this._onPageShow);removeEventListener('focus',this._onFocus);
      this.stopInteractionCapture?.();this.stopInteractionCapture=null;
      for(const id of [...this.modules.keys()])await this._removeModule(id,reason,false);
      this._emitAction('core.load','inactive',{kind:'system',behavior:'stateful',name:'Load Core',reason});
      this._emitAction('session.presence','inactive',{kind:'system',behavior:'stateful',name:'Session Presence',reason});
      await this._sendLifecycleClose(reason,[],false);
      await this._flushQueuedLogs();
      this.bus.close();
    }
    _schedulePoll(){
      clearTimeout(this.pollTimer);if(!this.running)return;
      this.pollTimer=setTimeout(async()=>{try{await this.refresh(false)}catch(err){this._emitEngineError(err)}this._schedulePoll()},CFG.discoveryIntervalMs);
    }
    _isMobileViewport(){return this.mobileMedia?this.mobileMedia.matches:window.innerWidth<=767}
    _hideOnMobile(descriptor){return descriptor?.presentation?.responsive?.hideOnMobile===true}
    _moduleAllowedForViewport(descriptor){return !(this._hideOnMobile(descriptor)&&this._isMobileViewport())}
    _bindResponsiveModuleVisibility(){if(this.mobileMedia?.addEventListener)this.mobileMedia.addEventListener('change',this._onResponsiveModuleVisibilityChange);else addEventListener('resize',this._onResponsiveModuleVisibilityChange,{passive:true})}
    _unbindResponsiveModuleVisibility(){if(this.mobileMedia?.removeEventListener)this.mobileMedia.removeEventListener('change',this._onResponsiveModuleVisibilityChange);else removeEventListener('resize',this._onResponsiveModuleVisibilityChange)}
    _isBootstrapLoader(descriptor){return descriptor?.module?.bootstrap?.role==='loader'}
    _moduleOrderDisplay(descriptor){return this._isBootstrapLoader(descriptor)?'BOOT':String(this._moduleOrder(descriptor)).padStart(5,'0')}
    _bootstrapLoaderRecord(){return this.bootstrapLoaderId?this.modules.get(this.bootstrapLoaderId):null}
    async _prepareBootstrapLoader(modules=[]){
      const loader=modules.find(d=>this._isBootstrapLoader(d));
      if(!loader){this.bootstrapLoaderId=null;this.bootstrapLoadState=null;return}
      this.bootstrapLoaderId=loader.action.id;
      const targets=modules.filter(d=>!this._isBootstrapLoader(d));
      this.bootstrapLoadState={total:targets.length,loaded:0,failed:0,current:null,completedIds:new Set(),failedIds:new Set()};
      if(!this.modules.has(loader.action.id))await this._addModule(loader,{bootstrap:true,skipProgress:true});
      const record=this._bootstrapLoaderRecord();
      if(record?.instance?.setProgress)await record.instance.setProgress({loaded:0,total:targets.length,failed:0,current:null,phase:'loading'});
    }
    async _bootstrapBegin(descriptor){
      const state=this.bootstrapLoadState;if(!state||!descriptor||this._isBootstrapLoader(descriptor))return;
      const id=descriptor.action?.id;if(!id)return;
      state.current=id;
      const record=this._bootstrapLoaderRecord();
      if(record?.instance?.setProgress)await record.instance.setProgress({loaded:state.loaded,total:state.total,failed:state.failed,current:id,phase:'loading'});
    }
    async _bootstrapProgress(descriptor,status='loaded'){
      const state=this.bootstrapLoadState;if(!state||!descriptor||this._isBootstrapLoader(descriptor))return;
      const id=descriptor.action?.id;if(!id)return;
      if(status==='loaded'&&!state.completedIds.has(id)){state.completedIds.add(id);state.loaded++}
      if(status==='failed'&&!state.failedIds.has(id)){state.failedIds.add(id);state.failed++}
      if(state.current===id)state.current=null;
      const record=this._bootstrapLoaderRecord();
      if(record?.instance?.setProgress)await record.instance.setProgress({loaded:state.loaded,total:state.total,failed:state.failed,current:null,phase:'loading'});
    }
    async _finishBootstrapLoader(){
      const state=this.bootstrapLoadState,record=this._bootstrapLoaderRecord();if(!state||!record)return;
      if(record.instance?.setProgress)await record.instance.setProgress({loaded:state.loaded,total:state.total,failed:state.failed,current:null,phase:'complete'});
      if(record.instance?.finish)await record.instance.finish({loaded:state.loaded,total:state.total,failed:state.failed});
      if(this.activeActions.has(this.bootstrapLoaderId))await this.deactivate(this.bootstrapLoaderId,{reason:'bootstrap-complete'});
      this.bootstrapLoadState=null;
    }
    _moduleOrder(descriptor){
      if(this._isBootstrapLoader(descriptor))return -1;
      const policy=CFG.moduleOrdering||{};
      const reservedId=policy.reservedFirstActionId||'core.ui.header-bar';
      const reservedOrder=Number(policy.reservedFirstOrder??0);
      const profileId=policy.reservedProfileActionId||'core.user.profile';
      const profileOrder=Number(policy.reservedProfileOrder??10);
      const lastId=policy.reservedLastActionId||'project.system.update-log';
      const lastOrder=Number(policy.reservedLastOrder??99999);
      const minNormal=Number(policy.minNonReservedOrder??1);
      const fallback=Number(policy.defaultOrder??50000);
      const id=String(descriptor?.action?.id||'');
      if(id===reservedId)return reservedOrder;
      if(id===profileId)return profileOrder;
      if(id===lastId)return lastOrder;
      const parsed=Number.parseInt(String(descriptor?.module?.order??''),10);
      return Number.isFinite(parsed)?Math.max(minNormal,parsed):fallback;
    }
    _sortDescriptors(modules=[]){
      return [...modules].sort((a,b)=>this._moduleOrder(a)-this._moduleOrder(b)||String(a?.action?.id||'').localeCompare(String(b?.action?.id||'')));
    }
    _presentation(descriptor){return descriptor?.presentation||{}}
    _presentationOrder(descriptor){
      const raw=this._presentation(descriptor)?.layout?.order;
      const parsed=Number.parseInt(String(raw??''),10);
      return Number.isFinite(parsed)?parsed:this._moduleOrder(descriptor);
    }
    _findRegion(name){
      if(!name||name==='root')return this.mountRoot;
      return [...document.querySelectorAll('[data-loom-region]')].find(n=>n?.dataset?.loomRegion===String(name))||null;
    }
    _resolveMountTarget(descriptor,fallbackSelector=null){
      const mount=this._presentation(descriptor)?.mount||{};
      const region=this._findRegion(mount.region);
      if(region){
        if(mount.slot){
          const slot=[...region.querySelectorAll('[data-loom-slot]')].find(n=>n?.dataset?.loomSlot===String(mount.slot));
          if(slot)return slot;
        }else return region;
      }
      if(fallbackSelector){const fallback=document.querySelector(fallbackSelector);if(fallback)return fallback}
      return this.mountRoot;
    }
    _applyPresentation(node,descriptor){
      if(!node)return node;
      const p=this._presentation(descriptor),layout=p.layout||{};
      node.dataset.module=node.dataset.module||descriptor.action.id;
      node.dataset.moduleOrder=this._moduleOrderDisplay(descriptor);
      if(p.role)node.dataset.loomRole=String(p.role);
      if(p.role==='region'&&p.region)node.dataset.loomRegion=String(p.region);
      if(layout.className)for(const cls of String(layout.className).split(/\s+/).filter(Boolean))node.classList.add(cls);
      const widthScope=String(layout.widthScope||'container').toLowerCase();
      if(widthScope==='page')node.dataset.loomWidthScope='page';else node.removeAttribute('data-loom-width-scope');
      const width={full:'100%',content:'fit-content',auto:'auto'}[layout.width];if(width)node.style.width=width;
      const align={start:'flex-start',center:'center',end:'flex-end',stretch:'stretch'}[layout.align];if(align)node.style.alignSelf=align;
      if(layout.position&&layout.position!=='flow')node.style.position=String(layout.position);
      if(layout.layer!=null)node.style.zIndex=String(layout.layer);
      if(layout.maxWidth)node.style.maxWidth=String(layout.maxWidth);
      if(layout.minHeight)node.style.minHeight=String(layout.minHeight);
      if(layout.margin)node.style.margin=String(layout.margin);
      if(layout.padding)node.style.padding=String(layout.padding);
      const offsetX=layout.offsetX!=null?String(layout.offsetX):'0px';
      const offsetY=layout.offsetY!=null?String(layout.offsetY):'0px';
      if(layout.offsetX!=null||layout.offsetY!=null)node.style.translate=`${offsetX} ${offsetY}`;
      node.style.order=String(this._presentationOrder(descriptor));
      return node;
    }
    _moduleLayoutStorageKey(){return `loom:${this.project}:module-layout:${this.identity.clientId}`}
    _normalizeModuleLayoutState(){
      if(!this.moduleLayoutState||typeof this.moduleLayoutState!=='object')this.moduleLayoutState={collapsed:{}};
      if(!this.moduleLayoutState.collapsed||typeof this.moduleLayoutState.collapsed!=='object')this.moduleLayoutState.collapsed={};
    }
    _applyHydratedModuleLayoutState(){
      for(const frame of document.querySelectorAll('[data-loom-frame-for]')){
        const id=frame.dataset.loomFrameFor,record=this.modules.get(id);if(!record)continue;
        const policy=this._moduleFramePolicy(record.descriptor,this.mountRoot);if(!policy.collapseEnabled){frame.classList.remove('is-collapsed');continue}
        const saved=Object.prototype.hasOwnProperty.call(this.moduleLayoutState?.collapsed||{},id);const collapsed=saved?!!this.moduleLayoutState.collapsed[id]:policy.initialCollapsed;
        frame.classList.toggle('is-collapsed',collapsed);frame.querySelector('.loom-module-frame-head')?.setAttribute('aria-expanded',collapsed?'false':'true');
      }
    }
    _loadModuleLayoutState(){
      let hasLocal=false;
      try{const local=JSON.parse(localStorage.getItem(this._moduleLayoutStorageKey())||'null');if(local&&typeof local==='object'){this.moduleLayoutState=local;hasLocal=true}}catch{}
      this._normalizeModuleLayoutState();
      if(hasLocal)return;
      this._hydrateModuleLayoutStateRemote();
    }
    async _hydrateModuleLayoutStateRemote(){
      const controller=typeof AbortController!=='undefined'?new AbortController():null;
      const timeout=Math.max(1000,Number(CFG?.performance?.moduleLayoutHydrateTimeoutMs||2500));
      const timer=controller?setTimeout(()=>controller.abort('module-layout-timeout'),timeout):null;
      try{
        const r=await fetch(`${this.apiBase}/project-state.php`,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',...(controller?{signal:controller.signal}:{}),body:JSON.stringify({action:'get',project:this.project,moduleId:'core.ui.module-layout',clientId:this.identity.clientId})});
        if(r.ok){const j=await r.json();if(j?.state&&typeof j.state==='object'){this.moduleLayoutState={collapsed:{},...j.state};this._normalizeModuleLayoutState();try{localStorage.setItem(this._moduleLayoutStorageKey(),JSON.stringify(this.moduleLayoutState))}catch{}this._applyHydratedModuleLayoutState()}}
      }catch{}finally{if(timer)clearTimeout(timer)}
    }
    _persistModuleLayoutState(){
      try{localStorage.setItem(this._moduleLayoutStorageKey(),JSON.stringify(this.moduleLayoutState))}catch{}
      fetch(`${this.apiBase}/project-state.php`,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({action:'save',project:this.project,moduleId:'core.ui.module-layout',clientId:this.identity.clientId,state:this.moduleLayoutState})}).catch(()=>{});
    }
    _ensureModuleFrameStyles(){
      if(this.moduleFrameStyle?.isConnected)return;
      const st=document.createElement('style');st.dataset.loomCore='module-collapse';st.textContent=`
        .loom-module-frame{width:100%;max-width:100%;min-width:0;display:flex;flex-direction:column;border-radius:28px;position:relative;box-sizing:border-box}
        .loom-module-frame-head{height:40px;min-width:0;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 12px 0 16px;border:1px solid rgba(44,103,60,.16);border-bottom:0;border-radius:28px 28px 0 0;background:rgba(255,255,255,.93);backdrop-filter:blur(12px);font:850 10px/1 Inter,ui-sans-serif,system-ui;color:#52665a;letter-spacing:.015em;cursor:pointer;user-select:none;position:relative;z-index:2;box-sizing:border-box}
        .loom-module-frame-head>span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .loom-module-frame-head button{width:28px;height:28px;flex:0 0 28px;border:0;border-radius:8px;background:#edf5ef;color:#4c6956;font:900 13px/1 system-ui;cursor:pointer;display:grid;place-items:center;transition:.16s ease}
        .loom-module-frame.no-collapse .loom-module-frame-head{cursor:default}
        .loom-module-frame.no-collapse .loom-module-frame-head button{display:none}
        .loom-module-frame-body{min-width:0;width:100%;max-width:100%;margin-top:-1px;box-sizing:border-box}
        .loom-module-frame:not(.is-collapsed) .loom-module-frame-body{display:block}
        .loom-module-frame:not(.is-collapsed) .loom-module-frame-body>[data-loom-module-content]{width:100%;max-width:100%;min-width:0;box-sizing:border-box;border-top-left-radius:0!important;border-top-right-radius:0!important;margin-top:0!important}
        .loom-module-frame.is-collapsed .loom-module-frame-body{display:none}
        .loom-module-frame.is-collapsed .loom-module-frame-head{border:1px solid rgba(44,103,60,.16);border-radius:18px;background:rgba(255,255,255,.94);box-shadow:0 8px 28px rgba(19,72,37,.05)}
        .loom-module-frame.is-collapsed .loom-module-frame-head button{transform:rotate(-90deg)}
        .loom-orb-captured.loom-module-frame .loom-module-frame-head{display:none!important}
        .loom-orb-captured.loom-module-frame .loom-module-frame-body{display:block!important}
        @media(max-width:520px){
          .loom-module-frame{border-radius:22px}
          .loom-module-frame-head{height:42px;padding:0 9px 0 13px;border-radius:22px 22px 0 0;font-size:10px}
          .loom-module-frame.is-collapsed .loom-module-frame-head{border-radius:16px}
        }
      `;document.head.appendChild(st);this.moduleFrameStyle=st;
    }
    _moduleFramePolicy(descriptor,host){
      const p=this._presentation(descriptor),chrome=p?.chrome||{};
      const eligible=p?.role==='content'&&host===this.mountRoot;
      let titleBarVisible=chrome.titleBarVisible;
      if(typeof titleBarVisible!=='boolean')titleBarVisible=p?.collapsible!==false;
      let collapseEnabled=chrome.collapseEnabled;
      if(typeof collapseEnabled!=='boolean')collapseEnabled=p?.collapsible!==false;
      if(!titleBarVisible)collapseEnabled=false;
      const titleText=String(chrome.titleText||'').trim();
      return {eligible,titleBarVisible:!!titleBarVisible,collapseEnabled:!!collapseEnabled,initialCollapsed:!!chrome.initialCollapsed,titleText};
    }
    _shouldFrameModule(descriptor,host){const policy=this._moduleFramePolicy(descriptor,host);return policy.eligible&&policy.titleBarVisible}
    _createModuleFrame(descriptor,node){
      this._ensureModuleFrameStyles();const id=descriptor.action.id,policy=this._moduleFramePolicy(descriptor,this.mountRoot);
      const titleName=policy.titleText||String(descriptor.action.name||id);
      const frame=document.createElement('section');frame.className=`loom-module-frame${policy.collapseEnabled?'':' no-collapse'}`;frame.dataset.module=id;frame.dataset.loomFrameFor=id;frame.setAttribute('aria-label',titleName);
      const safeName=String(titleName).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      const head=document.createElement('div');head.className='loom-module-frame-head';head.innerHTML=`<span>${safeName}</span>${policy.collapseEnabled?`<button type="button" aria-label="Collapse or expand ${String(titleName||'module').replace(/["<>]/g,'')}">⌄</button>`:''}`;
      const body=document.createElement('div');body.className='loom-module-frame-body';node.removeAttribute('data-module');node.dataset.loomModuleContent=id;body.appendChild(node);frame.append(head,body);
      const apply=collapsed=>{const next=policy.collapseEnabled&&!!collapsed;frame.classList.toggle('is-collapsed',next);head.setAttribute('aria-expanded',next?'false':'true')};
      const saved=Object.prototype.hasOwnProperty.call(this.moduleLayoutState?.collapsed||{},id);
      apply(policy.collapseEnabled?(saved?!!this.moduleLayoutState.collapsed[id]:policy.initialCollapsed):false);
      if(policy.collapseEnabled)head.addEventListener('click',()=>{const collapsed=!frame.classList.contains('is-collapsed');apply(collapsed);if(collapsed)this.moduleLayoutState.collapsed[id]=true;else delete this.moduleLayoutState.collapsed[id];this._persistModuleLayoutState();this._log({type:collapsed?'module.ui.collapsed':'module.ui.expanded',actionId:id,name:descriptor.action.name||id})});
      return frame;
    }
    _mountModuleNode(descriptor,node,fallbackSelector=null){
      const host=this._resolveMountTarget(descriptor,fallbackSelector);
      if(this._shouldFrameModule(descriptor,host)){
        const frame=this._createModuleFrame(descriptor,node);this._applyPresentation(frame,descriptor);host.appendChild(frame);
      }else{this._applyPresentation(node,descriptor);host.appendChild(node)}
      this._reflowModuleOrder();
      return host;
    }
    _reflowModuleOrder(){
      if(!this.mountRoot)return;
      for(const node of document.querySelectorAll('[data-module]')){
        const id=node?.dataset?.module,record=this.modules.get(id);if(!record)continue;
        const order=node.parentElement===this.mountRoot?this._moduleOrder(record.descriptor):this._presentationOrder(record.descriptor);
        node.style.order=String(order);node.dataset.moduleOrder=this._moduleOrderDisplay(record.descriptor);
      }
    }
    _preloadModuleEntries(modules=[]){
      for(const descriptor of modules){
        const raw=descriptor?.entry_url;if(!raw)continue;
        const href=this._cacheBustUrl(this._resolveRuntimeUrl(raw),descriptor.fingerprint||CFG?.cacheBust?.staticVersion||Date.now());
        if(this.modulePreloads.has(href))continue;
        this.modulePreloads.add(href);
        const link=document.createElement('link');link.rel='modulepreload';link.href=href;link.crossOrigin='anonymous';link.dataset.loomPreload=descriptor.action?.id||'';document.head.appendChild(link);
      }
    }
    async _loadModuleBatch(descriptors=[],options={}){
      const list=[...descriptors];if(!list.length)return;
      const limit=Math.max(1,Math.min(8,Number(CFG?.performance?.moduleLoadConcurrency||4))),workers=Math.min(limit,list.length);let cursor=0;
      await Promise.all(Array.from({length:workers},async()=>{while(true){const i=cursor++;if(i>=list.length)return;await this._addModule(list[i],options)}}));
    }
    async refresh(isBoot=false){
      if(isBoot)await this._coreStep('discover-modules',async()=>{});
      const payload=await this.registry.load({startup:isBoot});
      payload.modules=this._sortDescriptors(payload.modules||[]);
      this.discoveredDescriptors=payload.modules;
      const runtimeModules=payload.modules.filter(descriptor=>this._moduleAllowedForViewport(descriptor));
      this._indexUserActions(runtimeModules);
      if(isBoot)this._preloadModuleEntries(runtimeModules);
      if(isBoot)await this._coreStep('validate-manifests',async()=>{});
      if(isBoot)await this._prepareBootstrapLoader(runtimeModules);
      const incoming=new Map(runtimeModules.map(m=>[m.action.id,m]));
      for(const [id,current] of [...this.modules]){
        const next=incoming.get(id);
        if(!next)await this._removeModule(id,'module-removed');
        else if(next.fingerprint!==current.descriptor.fingerprint){await this._removeModule(id,'module-changed',false);await this._addModule(next,{skipProgress:!isBoot})}
      }
      const additions=runtimeModules.filter(descriptor=>!(isBoot&&this._isBootstrapLoader(descriptor))&&!this.modules.has(descriptor.action.id));
      if(isBoot){
        /* Region providers and shell/controller modules establish capture/mount targets first;
           independent content modules then load concurrently. Controllers are structural because
           they may need to observe/rehome content the instant it mounts (for example Profile Dock). */
        const structuralRoles=new Set(['region','controller']);
        const structural=additions.filter(d=>structuralRoles.has(d?.presentation?.role)),ordinary=additions.filter(d=>!structuralRoles.has(d?.presentation?.role));
        for(const descriptor of structural)await this._addModule(descriptor,{skipProgress:false});
        await this._loadModuleBatch(ordinary,{skipProgress:false});
      }else{
        for(const descriptor of additions)await this._addModule(descriptor,{skipProgress:true});
      }
      this._reflowModuleOrder();
      this.bus.emit({type:'registry.snapshot',runtimeId:this.runtimeId,source:this.registry.lastSource,mobileViewport:this._isMobileViewport(),modules:payload.modules.map(m=>({id:m.action.id,fingerprint:m.fingerprint,order:this._moduleOrder(m),orderDisplay:this._moduleOrderDisplay(m),bootstrap:this._isBootstrapLoader(m),hideOnMobile:this._hideOnMobile(m),suppressedForMobile:!this._moduleAllowedForViewport(m),userActions:(m.user_actions||[]).map(a=>a.id)}))});
      if(isBoot)await this._finishBootstrapLoader();
    }
    _indexUserActions(modules=[]){
      this.userActionDescriptors.clear();
      for(const descriptor of modules){
        for(const ua of descriptor.user_actions||[]){
          if(!ua?.id)continue;
          this.userActionDescriptors.set(ua.id,{...ua,kind:'user',behavior:ua.behavior||'transient',parent:descriptor.action.id,moduleActionId:descriptor.action.id,moduleName:descriptor.action.name});
        }
      }
    }
    _userActionDescriptor(id){return this.userActionDescriptors.get(id)||null}
    _userActionMeta(id){
      const ua=this._userActionDescriptor(id);if(!ua)throw new Error(`Undeclared user action ${id}`);
      return {name:ua.name,description:ua.description||'',kind:'user',behavior:ua.behavior||'transient',parent:ua.parent||ua.moduleActionId,moduleActionId:ua.moduleActionId,moduleName:ua.moduleName,actor:'user',source:'module-ui'};
    }
    async _withTimeout(work,ms,label){
      let timer=null,remaining=Math.max(1,Number(ms)||1),last=Date.now();
      const timeout=new Promise((_,reject)=>{
        const tick=()=>{
          const now=Date.now();
          if(window.LoomDeploymentGuard?.active){last=now;timer=setTimeout(tick,750);return;}
          remaining-=Math.max(0,now-last);last=now;
          if(remaining<=0){reject(new Error(`${label} timed out after ${ms}ms`));return;}
          timer=setTimeout(tick,Math.min(750,remaining));
        };
        timer=setTimeout(tick,Math.min(750,remaining));
      });
      try{return await Promise.race([Promise.resolve(work),timeout])}
      finally{if(timer)clearTimeout(timer)}
    }
    async _moduleLog(descriptor,type,detail={}){
      const matches=(descriptor.user_actions||[]).filter(a=>Array.isArray(a.events)&&a.events.includes(type));
      for(const a of matches){const meta=this._userActionMeta(a.id);await this._emitUserActionState(a.id,'active',{...meta,domainEvent:type,...detail})}
      const event={type,actionId:descriptor.action.id,...detail};
      await this._log(event);
      for(const a of matches){const meta=this._userActionMeta(a.id);await this._emitUserActionState(a.id,'completed',{...meta,domainEvent:type,...detail})}
      return event;
    }
    async _addModule(descriptor,options={}){
      const id=descriptor.action.id;
      if(!options.skipProgress)await this._bootstrapBegin(descriptor);
      try{
        const record={descriptor,instance:null,styles:[],cleanup:null};this.modules.set(id,record);
        if(descriptor.styles?.length)this._attachStyles(descriptor);
        const moduleUrl=this._cacheBustUrl(this._resolveRuntimeUrl(descriptor.entry_url),descriptor.fingerprint||Date.now());
        const imported=await this._withTimeout(import(moduleUrl),Math.max(2000,Number(CFG?.performance?.moduleImportTimeoutMs||8000)),`Module import ${id}`),factory=imported.createModule||imported.default;
        if(typeof factory!=='function')throw new Error(`Module ${id} must export createModule(ctx)`);
        const ctx=this._createContext(descriptor,record);record.instance=await this._withTimeout(factory(ctx),Math.max(1500,Number(CFG?.performance?.moduleFactoryTimeoutMs||5000)),`Module factory ${id}`);if(record.instance?.mount)await this._withTimeout(record.instance.mount(),Math.max(2000,Number(CFG?.performance?.moduleMountTimeoutMs||7000)),`Module mount ${id}`);
        this.bus.emit({type:'module.available',actionId:id,name:descriptor.action.name,kind:descriptor.action.kind,behavior:descriptor.action.behavior,parent:descriptor.action.parent||'core.load',fingerprint:descriptor.fingerprint,order:this._moduleOrder(descriptor),orderDisplay:this._moduleOrderDisplay(descriptor),bootstrap:this._isBootstrapLoader(descriptor)});
        await this._log({type:'module.available',actionId:id,name:descriptor.action.name,kind:descriptor.action.kind,behavior:descriptor.action.behavior,order:this._moduleOrder(descriptor),orderDisplay:this._moduleOrderDisplay(descriptor),bootstrap:this._isBootstrapLoader(descriptor)});
        if(descriptor.action.autostart||options.bootstrap)await this._withTimeout(this.activate(id,{trigger:options.bootstrap?'bootstrap':'autostart'}),Math.max(2000,Number(CFG?.performance?.moduleActivationTimeoutMs||7000)),`Module activation ${id}`);
        if(!options.skipProgress)await this._bootstrapProgress(descriptor,'loaded');
        return true;
      }catch(err){
        const record=this.modules.get(id);
        try{if(record?.instance?.unmount)await this._withTimeout(record.instance.unmount({reason:'load-failed'}),2500,`Module cleanup ${id}`)}catch{}
        if(record)this._detachStyles(record);
        for(const n of [...document.querySelectorAll('[data-loom-frame-for]')])if(n.dataset.loomFrameFor===id)n.remove();
        this.modules.delete(id);this.activeActions.delete(id);
        this.bus.emit({type:'module.error',actionId:id,message:String(err.message||err)});
        await this._log({type:'module.error',actionId:id,message:String(err.message||err)});console.error(err);
        if(!options.skipProgress)await this._bootstrapProgress(descriptor,'failed');
        return false;
      }
    }
    async _removeModule(id,reason='removed',capabilityRemoved=true){
      const record=this.modules.get(id);if(!record)return;
      try{
        if(this.activeActions.has(id))await this.deactivate(id,{reason});
        if(record.instance?.unmount)await record.instance.unmount({reason});
      }catch(err){console.warn(err)}
      this._detachStyles(record);for(const n of [...document.querySelectorAll('[data-loom-frame-for]')])if(n.dataset.loomFrameFor===id)n.remove();this.modules.delete(id);
      const type=capabilityRemoved?'module.removed':'module.unloaded';
      this.bus.emit({type,actionId:id,reason});await this._log({type,actionId:id,reason});
    }
    async activate(id,detail={}){
      const record=this.modules.get(id);if(!record)throw new Error(`Unknown action ${id}`);
      const action=record.descriptor.action;if(action.kind==='user'&&detail.trigger==='autostart')return;
      if(action.behavior==='transient')return this.runTransient(id,()=>record.instance?.activate?.(detail),detail);
      if(this.activeActions.has(id))return;
      this._emitAction(id,'active',{...detail,...action});
      this.activeActions.set(id,{since:new Date().toISOString(),behavior:action.behavior,name:action.name,kind:action.kind});
      try{
        const cleanup=await record.instance?.activate?.(detail);if(typeof cleanup==='function')record.cleanup=cleanup;
        this._reflowModuleOrder();
      }catch(err){
        this.activeActions.delete(id);this._emitAction(id,'failed',{...action,message:String(err.message||err)});throw err;
      }
    }
    async deactivate(id,detail={}){
      const record=this.modules.get(id);if(!record)return;
      try{if(record.instance?.deactivate)await record.instance.deactivate(detail);if(record.cleanup)await record.cleanup()}
      finally{record.cleanup=null;this.activeActions.delete(id);this._emitAction(id,'inactive',{...detail,...record.descriptor.action})}
    }
    async runTransient(id,fn,detail={}){
      const record=this.modules.get(id);if(!record)throw new Error(`Unknown action ${id}`);const action=record.descriptor.action;
      this._emitAction(id,'active',{...detail,...action});
      try{const result=await fn?.();this._emitAction(id,'completed',{...detail,...action});return result}
      catch(err){this._emitAction(id,'failed',{...detail,...action,message:String(err.message||err)});throw err}
    }
    async userAction(id,detail={}){return this.runUserAction(id,async()=>undefined,detail)}
    async runUserAction(id,fn,detail={}){
      const meta=this._userActionMeta(id),behavior=meta.behavior||'transient';
      await this._emitUserActionState(id,'active',{...meta,...detail});
      if(behavior==='stateful'||behavior==='pending')this.activeUserActions.set(id,{since:new Date().toISOString(),...meta});
      try{const result=await fn?.();if(behavior!=='stateful'&&behavior!=='pending')await this._emitUserActionState(id,'completed',{...meta,...detail});return result}
      catch(err){this.activeUserActions.delete(id);await this._emitUserActionState(id,'failed',{...meta,...detail,message:String(err.message||err)});throw err}
    }
    async beginUserAction(id,detail={}){const meta=this._userActionMeta(id);this.activeUserActions.set(id,{since:new Date().toISOString(),...meta});await this._emitUserActionState(id,'active',{...meta,...detail})}
    async completeUserAction(id,detail={}){const meta=this._userActionMeta(id);this.activeUserActions.delete(id);await this._emitUserActionState(id,'completed',{...meta,...detail})}
    async failUserAction(id,message,detail={}){const meta=this._userActionMeta(id);this.activeUserActions.delete(id);await this._emitUserActionState(id,'failed',{...meta,...detail,message})}
    async beginPending(id,detail={}){
      const record=this.modules.get(id);if(!record)throw new Error(`Unknown action ${id}`);const action=record.descriptor.action;
      this.activeActions.set(id,{since:new Date().toISOString(),behavior:'pending',name:action.name,kind:action.kind});
      this._emitAction(id,'active',{...detail,...action});
    }
    async completePending(id,detail={}){const r=this.modules.get(id);this.activeActions.delete(id);if(r)this._emitAction(id,'completed',{...detail,...r.descriptor.action})}
    async failPending(id,message,detail={}){const r=this.modules.get(id);this.activeActions.delete(id);if(r)this._emitAction(id,'failed',{...detail,...r.descriptor.action,message})}
    _createContext(descriptor,record){
      const runtime=this,id=descriptor.action.id;
      return Object.freeze({
        project:this.project,identity:this.identity,runtimeId:this.runtimeId,apiBase:this.apiBase,action:descriptor.action,module:descriptor.module,config:descriptor.config||{},capabilities:descriptor.capabilities||{},presentation:descriptor.presentation||{},mountRoot:this.mountRoot,descriptor,order:runtime._moduleOrder(descriptor),orderDisplay:runtime._moduleOrderDisplay(descriptor),
        listModuleDescriptors:()=>[...runtime.discoveredDescriptors],getModuleDescriptor:actionId=>runtime.discoveredDescriptors.find(d=>d?.action?.id===actionId)||null,
        getExtensionProviders:extensionId=>[...runtime.modules.values()].map(r=>({descriptor:r.descriptor,provider:r.instance?.extensions?.[extensionId]})).filter(x=>x.provider),
        getExtension:extensionId=>{for(const r of runtime.modules.values()){const p=r.instance?.extensions?.[extensionId];if(p)return p}return null;},
        query:sel=>document.querySelector(sel),create:(tag,props={})=>Object.assign(document.createElement(tag),props),
        apiUrl:path=>`${runtime.apiBase}/${String(path||'').replace(/^\/+/, '')}`,
        fetchApi:(path,options={})=>fetch(`${runtime.apiBase}/${String(path||'').replace(/^\/+/, '')}`,{cache:'no-store',...options}),
        requestCapability:async(capability,ttl=300)=>{const r=await fetch(`${runtime.apiBase}/capabilities.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({project:runtime.project,actionId:id,clientId:runtime.identity.clientId,capability,ttl})});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Capability request denied');return j.grant},
        setUserLabel:async label=>{const clean=window.LoomIdentity?.setUserLabel?.(runtime.identity.clientId,label)||String(label||'').trim();if(!clean)return null;runtime.identity.userLabel=clean;window.LOOM_USER_LABEL=clean;runtime.bus.identity=runtime.identity;runtime.bus.emit({type:'identity.profile.updated',runtimeId:runtime.runtimeId,userLabel:clean});await runtime._log({type:'identity.profile.updated',actionId:id,userLabel:clean});runtime._sendHeartbeat('identity-profile-updated');return clean;},
        setUserId:async userId=>{const clean=window.LoomIdentity?.setUserId?.(runtime.identity.clientId,userId);runtime.identity.userId=clean;window.LOOM_USER_ID=clean;runtime.bus.identity=runtime.identity;runtime.bus.emit({type:'identity.user.updated',runtimeId:runtime.runtimeId,userId:clean});await runtime._log({type:'identity.user.updated',actionId:id,userId:clean});runtime._sendHeartbeat('identity-user-updated');return clean;},
        resolveMountTarget:(fallbackSelector=null)=>runtime._resolveMountTarget(descriptor,fallbackSelector),
        applyPresentation:node=>runtime._applyPresentation(node,descriptor),
        mount:(node,fallbackSelector=null)=>runtime._mountModuleNode(descriptor,node,fallbackSelector),
        cacheBustUrl:(url,version=null)=>runtime._cacheBustUrl(url,version),
        step(stepId,state='completed',detail={}){const e=runtime.bus.emit({type:'action.step',actionId:id,stepId,state,...detail});runtime._log(e)},
        emit(type,detail={}){return runtime.bus.emit({type,actionId:id,...detail})},log(type,detail={}){return runtime._moduleLog(descriptor,type,detail)},
        toast(message,options={}){return window.LoomToast?.show?.(message,{...options,sourceActionId:id})||null},
        run(fn,detail={}){return runtime.runTransient(id,fn,detail)},begin(detail={}){return runtime.beginPending(id,detail)},complete(detail={}){return runtime.completePending(id,detail)},fail(message,detail={}){return runtime.failPending(id,message,detail)},
        userAction(actionId,detail={}){return runtime.userAction(actionId,detail)},runUserAction(actionId,fn,detail={}){return runtime.runUserAction(actionId,fn,detail)},beginUserAction(actionId,detail={}){return runtime.beginUserAction(actionId,detail)},completeUserAction(actionId,detail={}){return runtime.completeUserAction(actionId,detail)},failUserAction(actionId,message,detail={}){return runtime.failUserAction(actionId,message,detail)},
        resolveAssetPath:async(path,scope='project')=>{
          let requestedPath=String(path||'').replace(/^\/+/, '');
          let requestScope=String(scope||'project');

          // Module-relative assets are resolved against the discovered module
          // folder, then passed through the existing project-root resolver.
          // This keeps the server resolver explicit/safe while making
          // ctx.resolveAssetPath('assets/x.svg','module') a real portable API.
          if(requestScope==='module'){
            const folder=String(descriptor.folder||'').replace(/^\/+|\/+$/g,'');
            if(!folder||!requestedPath)return null;
            requestedPath=`${folder}/${requestedPath}`;
            requestScope='project';
          }

          const url=`${runtime.apiBase}/resolve-asset.php?project=${encodeURIComponent(runtime.project)}&scope=${encodeURIComponent(requestScope)}&path=${encodeURIComponent(requestedPath)}&_=${Date.now()}`;
          try{
            const response=await fetch(url,{cache:'no-store'});
            if(response.ok){
              const data=await response.json();
              if(data.found)return data.url;
            }
          }catch{}
          return null;
        },
        // Legacy compatibility only. New modules should use resolveAssetPath().
        resolveAsset:async(name,scope='project')=>{
          const url=`${runtime.apiBase}/resolve-asset.php?project=${encodeURIComponent(runtime.project)}&scope=${encodeURIComponent(scope)}&name=${encodeURIComponent(name)}&_=${Date.now()}`;
          try{const response=await fetch(url,{cache:'no-store'});if(response.ok){const data=await response.json();if(data.found)return data.url}}catch{}
          return null;
        }
      });
    }
    _attachStyles(descriptor){
      const record=this.modules.get(descriptor.action.id),links=[];
      for(const url of descriptor.styles||[]){const link=document.createElement('link');link.rel='stylesheet';link.href=this._cacheBustUrl(this._resolveRuntimeUrl(url),descriptor.fingerprint||Date.now());link.dataset.loomModule=descriptor.action.id;document.head.appendChild(link);links.push(link)}
      if(record)record.styles=links;
    }
    _detachStyles(record){for(const node of record.styles||[])node.remove()}
    async _coreStep(stepId,fn){
      const active=this.bus.emit({type:'action.step',actionId:'core.load',stepId,state:'active'});this._log(active);
      try{const r=await fn();this.coreSteps.set(stepId,'completed');const done=this.bus.emit({type:'action.step',actionId:'core.load',stepId,state:'completed'});this._log(done);return r}
      catch(err){this.coreSteps.set(stepId,'failed');const fail=this.bus.emit({type:'action.step',actionId:'core.load',stepId,state:'failed',message:String(err.message||err)});this._log(fail);throw err}
    }
    _emitAction(actionId,state,detail={}){const event=this.bus.emit({type:'action.state',actionId,state,runtimeId:this.runtimeId,correlationId:this.correlationId,causeId:this.lastEventId,...detail});this.lastEventId=event.id;this._log(event)}
    async _emitUserActionState(actionId,state,detail={}){const event=this.bus.emit({type:'action.state',actionId,state,runtimeId:this.runtimeId,kind:'user',correlationId:this.correlationId,causeId:this.lastEventId,...detail});this.lastEventId=event.id;await this._log(event);return event}
    _emitEngineError(err){const e=this.bus.emit({type:'engine.error',message:String(err.message||err)});this._log(e)}
    _activeSnapshot(){
      const out=[
        {id:'session.presence',name:'Session Presence',kind:'system',behavior:'stateful'},
        {id:'core.load',name:'Load Core',kind:'system',behavior:'stateful'}
      ];
      for(const [id,s] of this.activeActions){const d=this.modules.get(id)?.descriptor?.action||{};out.push({id,name:d.name||s.name||id,kind:d.kind||s.kind||'system',behavior:d.behavior||s.behavior||'stateful'})}
      for(const [id,s] of this.activeUserActions)out.push({id,name:s.name||id,kind:'user',behavior:s.behavior||'pending',parent:s.parent||s.moduleActionId||null});
      const seen=new Set();return out.filter(a=>a.id&&!seen.has(a.id)&&(seen.add(a.id),true));
    }
    _bindPresenceActivity(){
      for(const type of this._presenceActivityEvents)addEventListener(type,this._onPresenceActivity,{capture:true,passive:true});
      document.addEventListener('visibilitychange',this._onVisibilityChange,{passive:true});
      addEventListener('online',this._onOnline,{passive:true});
    }
    _unbindPresenceActivity(){
      for(const type of this._presenceActivityEvents)removeEventListener(type,this._onPresenceActivity,{capture:true});
      document.removeEventListener('visibilitychange',this._onVisibilityChange);
      removeEventListener('online',this._onOnline);
    }
    _newRuntimeIdentity(){
      this.runtimeId=crypto?.randomUUID?.()||`${Date.now()}_${Math.random().toString(36).slice(2)}`;
      this.correlationId=`corr_${this.runtimeId}`;this.lastEventId=null;this.closeToken=null;
    }
    _restartInteractionCapture(){
      if(!window.LoomInteractionCapture)return;
      try{this.stopInteractionCapture?.()}catch{}
      this.stopInteractionCapture=window.LoomInteractionCapture.start({project:this.project,identity:this.identity,runtimeId:this.runtimeId,apiBase:this.apiBase});
    }
    _handlePageHide(event){
      if(this.closed)return;
      this.pageHideAt=Date.now();this._flushQueuedLogsBeacon();
      // A pagehide can be emitted by mobile browsers and history transitions even when the
      // document later becomes usable again. Send the server a truthful close for a real
      // unload, but DO NOT irreversibly tear down the in-page runtime here.
      if(event?.persisted)return;
      const activeActions=this._activeSnapshot();this.pageHideCloseSent=true;
      this._sendLifecycleClose('pagehide',activeActions,true);
    }
    _recoverFromPageHide(reason='pageshow'){
      if(!this.pageHideCloseSent)return false;
      this.pageHideCloseSent=false;this.pageHideAt=0;this._newRuntimeIdentity();this._restartInteractionCapture();
      this._log({type:'session.runtime-recovered',runtimeId:this.runtimeId,reason,userLabel:this.identity.userLabel});
      return true;
    }
    _ensureSessionLiveness(reason='liveness-check',force=false){
      if(!this.running||this.closed)return;
      const recovered=this._recoverFromPageHide(reason);
      if(recovered||force){this._scheduleHeartbeat(0);this._activityHeartbeat(reason,true);return}
      this._activityHeartbeat(reason,false);
    }
    _activityHeartbeat(reason='user-activity',force=false){
      if(!this.running||this.closed)return;
      const min=Math.max(250,Number(CFG.heartbeatActivityMinMs||1000));
      if(!force&&Date.now()-this.lastHeartbeatSentAt<min)return;
      this._sendHeartbeat(reason);
    }
    _scheduleHeartbeat(delay=null){
      clearTimeout(this.heartbeatTimer);if(!this.running||this.closed)return;
      const ms=Math.max(500,Number(delay??CFG.heartbeatIntervalMs??5000));
      this.heartbeatTimer=setTimeout(()=>this._sendHeartbeat('interval'),ms);
    }
    _scheduleHeartbeatRetry(){
      clearTimeout(this.heartbeatRetryTimer);if(!this.running||this.closed)return;
      const ms=Math.max(750,Number(CFG.heartbeatRetryMs||2500));
      this.heartbeatRetryTimer=setTimeout(()=>this._sendHeartbeat('retry'),ms);
    }
    async _sendHeartbeat(reason='interval'){
      if(!this.running||this.closed)return false;
      if(this.heartbeatInFlight){this._scheduleHeartbeat(Math.min(Number(CFG.heartbeatIntervalMs||5000),1500));return false}
      this.heartbeatInFlight=true;this.lastHeartbeatSentAt=Date.now();
      const activeActions=this._activeSnapshot();
      const payload={type:'runtime.heartbeat',runtimeId:this.runtimeId,activeActionIds:activeActions.map(a=>a.id),activeActions,coreActive:true,status:'active',heartbeatReason:reason};
      this.bus.emit(payload);
      const ok=await this._presence('active',{activeActions,heartbeatReason:reason});
      this.heartbeatInFlight=false;
      if(ok){this.lastHeartbeatAckAt=Date.now();clearTimeout(this.heartbeatRetryTimer);this._scheduleHeartbeat()}
      else{this.lastHeartbeatErrorAt=Date.now();this._scheduleHeartbeatRetry()}
      return ok;
    }
    _startHeartbeat(){
      clearTimeout(this.heartbeatTimer);clearTimeout(this.heartbeatRetryTimer);
      this._sendHeartbeat('runtime-start');
    }
    async _presence(status='active',extra={}){
      const activeActions=extra.activeActions||this._activeSnapshot();
      const body={project:this.project,clientId:this.identity.clientId,sessionId:this.identity.sessionId,userId:this.identity.userId,userLabel:this.identity.userLabel,runtimeId:this.runtimeId,status,
        activeActionIds:status==='active'?activeActions.map(a=>a.id):[],activeActions:status==='active'?activeActions:[],meta:this.identity.meta,clientTimestamp:new Date().toISOString(),leaseDurationMs:CFG.heartbeatLeaseMs,...extra};
      try{
        const response=await fetch(`${this.apiBase}/heartbeat.php`,{method:'POST',headers:{'Content-Type':'application/json','Cache-Control':'no-store'},cache:'no-store',body:JSON.stringify(body),keepalive:true});
        return response.ok;
      }catch{return false}
    }
    async _sendLifecycleClose(reason='closed',activeActions=[],preferBeacon=false){
      this.closeToken=this.closeToken||crypto?.randomUUID?.()||`${Date.now()}_${Math.random().toString(36).slice(2)}`;
      const body={event:'close',project:this.project,clientId:this.identity.clientId,sessionId:this.identity.sessionId,userId:this.identity.userId,userLabel:this.identity.userLabel,runtimeId:this.runtimeId,
        reason,closeToken:this.closeToken,activeActions,activeActionIds:activeActions.map(a=>a.id),meta:this.identity.meta,clientTimestamp:new Date().toISOString()};
      try{
        if(preferBeacon&&navigator.sendBeacon){const blob=new Blob([JSON.stringify(body)],{type:'application/json'});navigator.sendBeacon(`${this.apiBase}/lifecycle.php`,blob);return true;}
        await fetch(`${this.apiBase}/lifecycle.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),keepalive:true});return true;
      }catch{return false}
    }
    _storeLocalLog(body){
      try{const key=`loom:${this.project}:local-log`;const arr=JSON.parse(localStorage.getItem(key)||'[]');arr.push({serverTimestamp:null,clientTimestamp:new Date().toISOString(),...body});localStorage.setItem(key,JSON.stringify(arr.slice(-1000)))}catch{}
    }
    _kickLogDrain(){
      if(this.logDraining||!this.logQueue.length)return;
      this.logDraining=true;
      this.logDrainPromise=(async()=>{
        while(this.logQueue.length){
          const body=this.logQueue.shift();
          try{const response=await fetch(`${this.apiBase}/log.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),keepalive:true});if(!response.ok)throw new Error(`log ${response.status}`)}
          catch{this._storeLocalLog(body)}
        }
      })().finally(()=>{this.logDraining=false;this.logDrainPromise=null;if(this.logQueue.length)this._kickLogDrain()});
    }
    _flushQueuedLogsBeacon(){
      if(!navigator.sendBeacon||!this.logQueue.length)return;
      const pending=this.logQueue.splice(0);
      for(const body of pending){try{const blob=new Blob([JSON.stringify(body)],{type:'application/json'});if(!navigator.sendBeacon(`${this.apiBase}/log.php`,blob))this._storeLocalLog(body)}catch{this._storeLocalLog(body)}}
    }
    async _flushQueuedLogs(){
      this._kickLogDrain();
      const p=this.logDrainPromise;if(p)try{await p}catch{}
    }
    async _log(event){
      const body={project:this.project,runtimeId:this.runtimeId,clientId:this.identity.clientId,sessionId:this.identity.sessionId,userId:this.identity.userId,userLabel:this.identity.userLabel,...event};
      this.logQueue.push(body);this._kickLogDrain();
      return {queued:true};
    }
  }
  window.LoomActionRuntime=LoomActionRuntime;window.PegboardActionRuntime=LoomActionRuntime;
})();
