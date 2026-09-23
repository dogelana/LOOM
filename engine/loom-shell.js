// @loom-file release=0.15.20 revision=15 policy=package-priority
(() => {
  'use strict';

  function ensureFavicon(apiBase){
    try{
      const apiUrl=new URL(String(apiBase||'api').replace(/\/?$/,'/'),document.baseURI||location.href);
      const href=new URL('../assets/loom-logo.png',apiUrl);href.searchParams.set('v',window.LoomBrand?.version||window.LoomConfig?.engineVersion||'unknown');
      let link=document.querySelector('link[data-loom-favicon="1"]');
      if(!link){link=document.createElement('link');link.rel='icon';link.type='image/png';link.dataset.loomFavicon='1';document.head.appendChild(link)}
      link.href=href.href;
    }catch{}
  }
  function installRoot(apiBase){try{const apiUrl=new URL(String(apiBase||'api').replace(/\/?$/,'/'),document.baseURI||location.href);return new URL('../',apiUrl)}catch{return new URL('./',location.href)}}
  function canonicalAdminLinks({apiBase='api',project=''}={}){
    const base=installRoot(apiBase),p=String(project||new URLSearchParams(location.search).get('project')||'').replace(/[^a-z0-9_-]/gi,'').toLowerCase();
    const q=p?`&project=${encodeURIComponent(p)}`:'';
    return [
      {label:'⚙️ LOOM Settings',href:new URL(`admin/?tab=loom${q}`,base).href},
      {label:'🎨 Project Settings',href:new URL(`admin/?tab=project${q}`,base).href},
      {label:'👥 Users',href:new URL(`admin/?tab=users${q}`,base).href},
      {label:'🆔 Identities',href:new URL(`admin/?tab=identities${q}`,base).href},
      {label:'🛡️ Access',href:new URL(`admin/?tab=access${q}`,base).href},
      {label:'🗄️ Database',href:new URL(`admin/?tab=database${q}`,base).href},
      {label:'📊 Activity',href:new URL(`admin/activity/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href},
      {label:'🧩 Pegboard',href:new URL(`pegboard/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href},
      {label:'🧬 Action Registry',href:new URL(`registry/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href}
    ];
  }
  function projectAdminQuickLinks({apiBase='api',project=''}={}){
    const wanted=['Project Settings','Users','Access','Activity','Pegboard','Action Registry'];
    return canonicalAdminLinks({apiBase,project}).filter(item=>wanted.some(label=>item.label.includes(label)));
  }
  function adminLinksForStatus({apiBase='api',project='',status=null}={}){
    const all=canonicalAdminLinks({apiBase,project});
    if(!status)return all;
    const role=String(status?.projectRole||'member');
    if(status?.isAdmin||role==='system-owner'||role==='loom-admin')return all;
    const caps=new Set(Array.isArray(status?.capabilities)?status.capabilities:[]);
    const allowed=[];
    if(caps.has('project.settings')||caps.has('project.content'))allowed.push('Project Settings');
    if(caps.has('project.users'))allowed.push('Users');
    if(caps.has('project.access'))allowed.push('Access');
    if(caps.has('project.view'))allowed.push('Activity');
    if(caps.has('project.modules'))allowed.push('Pegboard','Action Registry');
    return all.filter(item=>allowed.some(label=>item.label.includes(label)));
  }
  function navConfig(settings){
    const x=settings?.settings?.['loom.navigation.chrome']||{};
    const mode=(v,f='both')=>['both','emoji','text'].includes(v)?v:f;
    return {
      adminToolsPresentation:x.adminToolsPresentation==='top-bar'?'top-bar':'drawer',
      adminDrawerSide:x.adminDrawerSide==='left'?'left':'right',
      home:{emoji:String(x.homeEmoji||'🏠'),label:String(x.homeLabel||'LOOM Home'),headerMode:mode(x.homeHeaderMode),footerMode:mode(x.homeFooterMode,'emoji')},
      profile:{emoji:String(x.profileEmoji||'👤'),label:String(x.profileLabel||'LOOM Profile'),headerMode:mode(x.profileHeaderMode),footerMode:mode(x.profileFooterMode,'emoji')}
    };
  }
  function buttonParts(def,mode='both'){
    const emoji=String(def?.emoji||''),label=String(def?.label||'');
    if(mode==='emoji')return `<span class="loom-global-nav-emoji" aria-hidden="true">${escapeHtml(emoji)}</span><span class="loom-global-nav-sr">${escapeHtml(label)}</span>`;
    if(mode==='text')return `<span>${escapeHtml(label)}</span>`;
    return `<span class="loom-global-nav-emoji" aria-hidden="true">${escapeHtml(emoji)}</span><span>${escapeHtml(label)}</span>`;
  }
  function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  function ensureAdminStyle(){
    if(document.getElementById('loom-shell-admin-zone-style'))return;
    const style=document.createElement('style');style.id='loom-shell-admin-zone-style';style.textContent=`
      .loom-global-nav-sr{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
      .loom-shell-admin-zone{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;padding:5px 7px;border:1px solid rgba(78,92,82,.16);border-radius:14px;background:rgba(255,255,255,.62)}
      .loom-shell-admin-zone .loom-shell-admin-label{font-size:8px;font-weight:950;letter-spacing:.11em;color:#718077;padding:0 3px;white-space:nowrap}.loom-shell-admin-zone a{white-space:nowrap}
      .loom-admin-drawer{--loom-admin-tab-visible:38px;position:fixed;z-index:2147482500;top:50%;transform:translateY(-50%);display:flex;align-items:stretch;transition:transform .22s cubic-bezier(.2,.75,.25,1);filter:drop-shadow(0 16px 28px rgba(18,48,27,.14));isolation:isolate}
      /* The gripper must be the viewport-facing edge in both orientations. */
      .loom-admin-drawer[data-side="right"]{right:0;flex-direction:row-reverse}.loom-admin-drawer[data-side="left"]{left:0;flex-direction:row}
      .loom-admin-drawer-panel{position:relative;z-index:1;width:min(280px,calc(100vw - 54px));padding:12px;border:1px solid rgba(198,218,203,.95);background:rgba(250,253,250,.98);backdrop-filter:blur(18px);display:grid;gap:6px;max-height:min(78vh,680px);overflow:auto;box-shadow:0 18px 48px rgba(18,48,27,.12)}
      .loom-admin-drawer[data-side="right"] .loom-admin-drawer-panel{border-radius:18px 0 0 18px}.loom-admin-drawer[data-side="left"] .loom-admin-drawer-panel{border-radius:0 18px 18px 0}
      .loom-admin-drawer-tab{position:relative;z-index:3;align-self:center;flex:0 0 var(--loom-admin-tab-visible);width:var(--loom-admin-tab-visible);border:1px solid rgba(198,218,203,.95);background:#173f27;color:#fff;font:950 10px/1 system-ui;padding:12px 8px;cursor:pointer;writing-mode:vertical-rl;letter-spacing:.08em;min-height:108px;box-shadow:0 8px 24px rgba(18,48,27,.18);opacity:1!important;visibility:visible!important}
      .loom-admin-drawer[data-side="right"] .loom-admin-drawer-tab{border-radius:12px 0 0 12px;margin-right:-1px}.loom-admin-drawer[data-side="left"] .loom-admin-drawer-tab{border-radius:0 12px 12px 0;transform:rotate(180deg);margin-left:-1px}
      .loom-admin-drawer-panel .loom-shell-admin-label{font:950 9px/1.2 system-ui;letter-spacing:.12em;color:#718077;padding:4px 5px 6px}.loom-admin-drawer-panel a{display:block;text-decoration:none;color:#234b30;background:#fff;border:1px solid #d9e7dc;border-radius:10px;padding:9px 10px;font:850 10px/1.2 system-ui}
      /* Hide only the panel. Keep the ADMIN TOOLS gripper fully visible and in front. */
      .loom-admin-drawer:not([data-open="true"])[data-side="right"]{transform:translate(calc(100% - var(--loom-admin-tab-visible)),-50%)}.loom-admin-drawer:not([data-open="true"])[data-side="left"]{transform:translate(calc(-100% + var(--loom-admin-tab-visible)),-50%)}
      @media(max-width:760px){.loom-shell-admin-zone{width:100%;justify-content:center}.loom-shell-admin-zone .loom-shell-admin-label{width:100%;text-align:center}.loom-admin-drawer{--loom-admin-tab-visible:40px}.loom-admin-drawer-panel{width:min(300px,calc(100vw - 48px));max-height:66vh}}
    `;document.head.appendChild(style);
  }
  function renderAdminLinks(host,{apiBase='api',project='',target='_self',withLabel=false,status=null}={}){
    if(!host)return null;ensureAdminStyle();host.replaceChildren();const links=adminLinksForStatus({apiBase,project,status});
    if(withLabel){const label=document.createElement('span');label.className='loom-shell-admin-label';label.textContent='ADMIN-ONLY TOOLS';host.appendChild(label)}
    for(const item of links){const a=document.createElement('a');a.href=item.href;a.textContent=item.label;if(target)a.target=target;if(target==='_blank')a.rel='noopener';host.appendChild(a)}return links;
  }
  function adminStatusCacheKey(identity,project=''){return `loom:admin-status:${identity?.clientId||'anon'}:${String(project||'global')}`}
  async function fetchAdminStatus(apiBase,identity,project=''){
    const fallback={isAdmin:false,projectRole:'member',capabilities:[]};
    if(!identity?.clientId)return fallback;
    const controller=typeof AbortController!=='undefined'?new AbortController():null;
    const timer=controller?setTimeout(()=>controller.abort('admin-status-timeout'),3500):null;
    try{
      const r=await fetch(`${String(apiBase||'api').replace(/\/$/,'')}/admin.php`,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',...(controller?{signal:controller.signal}:{}),body:JSON.stringify({action:'status',clientId:identity.clientId,project,claim:false})});
      const j=await r.json();if(!r.ok)return fallback;
      try{sessionStorage.setItem(adminStatusCacheKey(identity,project),JSON.stringify({storedAt:Date.now(),value:j}))}catch{}
      return j;
    }catch{return fallback}finally{if(timer)clearTimeout(timer)}
  }
  async function adminStatus(apiBase,identity,project=''){
    if(!identity?.clientId)return {isAdmin:false,projectRole:'member',capabilities:[]};
    let cached=null;try{cached=JSON.parse(sessionStorage.getItem(adminStatusCacheKey(identity,project))||'null')}catch{}
    if(cached?.value&&Date.now()-Number(cached.storedAt||0)<120000){fetchAdminStatus(apiBase,identity,project).catch(()=>{});return cached.value}
    const fresh=await fetchAdminStatus(apiBase,identity,project);
    if((fresh?.isAdmin||fresh?.projectRole!=='member')||!cached?.value)return fresh;
    return cached.value;
  }
  function removeExistingAdminChrome(){document.querySelectorAll('[data-loom-admin-drawer="1"]').forEach(x=>x.remove())}
  function mountAdminTools({apiBase='api',project='',target='_self',settings=null,host=null,status=null}={}){
    ensureAdminStyle();removeExistingAdminChrome();const cfg=navConfig(settings||{});
    if(cfg.adminToolsPresentation==='top-bar'){
      if(!host)return null;host.hidden=false;host.classList.add('loom-shell-admin-zone');renderAdminLinks(host,{apiBase,project,target,withLabel:true,status});return host;
    }
    if(host){host.hidden=true;host.replaceChildren()}
    const wrap=document.createElement('aside');wrap.className='loom-admin-drawer';wrap.dataset.loomAdminDrawer='1';wrap.dataset.side=cfg.adminDrawerSide;wrap.dataset.open='false';
    const panel=document.createElement('div');panel.className='loom-admin-drawer-panel';renderAdminLinks(panel,{apiBase,project,target,withLabel:true,status});
    const tab=document.createElement('button');tab.type='button';tab.className='loom-admin-drawer-tab';tab.textContent='ADMIN TOOLS';tab.setAttribute('aria-expanded','false');
    const setOpen=v=>{wrap.dataset.open=v?'true':'false';tab.setAttribute('aria-expanded',v?'true':'false')};tab.onclick=()=>setOpen(wrap.dataset.open!=='true');
    wrap.addEventListener('mouseenter',()=>setOpen(true));wrap.addEventListener('mouseleave',()=>setOpen(false));wrap.append(panel,tab);document.body.appendChild(wrap);return wrap;
  }

  async function mount(opts={}){
    if(!window.LoomBrand)throw new Error('LoomBrand is required before LoomShell.');
    const apiBase=opts.apiBase||'api';ensureFavicon(apiBase);
    const identity=opts.identity||window.LoomIdentity?.get?.('loom-global-shell');
    const settings=opts.settings||await LoomBrand.fetchSettings(apiBase);window.LoomToast?.configureGlobal?.(settings);
    const cfg=navConfig(settings),base=installRoot(apiBase),homeHref=new URL('home/',base).href;
    let profile=null;const header=document.getElementById(opts.headerId||'loomShellHeader'),footer=document.getElementById(opts.footerId||'loomShellFooter');
    if(header){
      const shellLinks=(opts.links||[]).filter(x=>!/loom home/i.test(String(x?.label||'')));
      await LoomBrand.mountShellHeader(header,{apiBase,settings,pageTitle:opts.pageTitle||'LOOM',links:shellLinks});
      const nav=header.querySelector('.loom-shell-chrome-links');
      if(nav){
        const home=document.createElement('a');home.className='loom-global-home-button';home.href=homeHref;home.innerHTML=buttonParts(cfg.home,cfg.home.headerMode);home.title=cfg.home.label;nav.prepend(home);
        if(window.LoomIdentityEntry){const switchBtn=document.createElement('button');switchBtn.type='button';switchBtn.className='loom-global-profile-button';switchBtn.innerHTML='<span class="pic" aria-hidden="true">🔄</span><span>Switch User</span>';switchBtn.onclick=()=>window.LoomIdentityEntry.show?.();nav.appendChild(switchBtn)}
        if(opts.profile!==false&&window.LoomGlobalProfile&&identity){const btn=document.createElement('button');btn.type='button';btn.className='loom-global-profile-button';btn.innerHTML=buttonParts(cfg.profile,cfg.profile.headerMode);btn.title=cfg.profile.label;nav.appendChild(btn);profile=LoomGlobalProfile.create({apiBase,identity,projectsProvider:opts.projectsProvider||null});profile.bindButton(btn)}
      }
      if(opts.adminTools!==false){const status=await adminStatus(apiBase,identity,opts.project||'');const canAdmin=!!status?.isAdmin||['system-owner','loom-admin','project-admin','project-manager'].includes(status?.projectRole);if(canAdmin)mountAdminTools({apiBase,project:opts.project||'',target:opts.adminTarget||'_self',settings,host:opts.adminHost||header.querySelector('[data-loom-admin-links]'),status})}
    }
    if(footer){
      await LoomBrand.mountShellFooter(footer,{apiBase,settings,navigation:cfg,homeHref});
      const fp=footer.querySelector('[data-loom-footer-profile]');if(fp&&profile)profile.bindButton(fp);
    }
    return {settings,identity,profile,navigation:cfg};
  }

  window.LoomShell=Object.freeze({mount,adminStatus,canonicalAdminLinks,projectAdminQuickLinks,adminLinksForStatus,renderAdminLinks,mountAdminTools,navConfig,buttonParts});
})();
