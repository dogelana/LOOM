// @loom-file release=0.15.42 revision=25 policy=package-priority
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
      {label:'🎁 Referrals',href:new URL(`admin/referrals/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href},
      {label:'📦 Backup & Restore',href:new URL('admin/backups/',base).href},
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
      profile:{emoji:String(x.profileEmoji||'👤'),label:String(x.profileLabel||'LOOM Profile'),headerMode:mode(x.profileHeaderMode),footerMode:mode(x.profileFooterMode,'emoji')},
      switchUser:{emoji:String(x.switchEmoji||'🔄'),label:String(x.switchLabel||'Switch User'),headerMode:mode(x.switchHeaderMode),footerMode:mode(x.switchFooterMode,'emoji')}
    };
  }
  function buttonParts(def,mode='both'){
    const emoji=String(def?.emoji||''),label=String(def?.label||'');
    if(mode==='emoji')return `<span class="loom-global-nav-emoji" aria-hidden="true">${escapeHtml(emoji)}</span><span class="loom-global-nav-sr">${escapeHtml(label)}</span>`;
    if(mode==='text')return `<span>${escapeHtml(label)}</span>`;
    return `<span class="loom-global-nav-emoji" aria-hidden="true">${escapeHtml(emoji)}</span><span>${escapeHtml(label)}</span>`;
  }
  function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  function ensureControlStyle(){
    if(document.getElementById('loom-shell-user-controls-style'))return;
    const style=document.createElement('style');style.id='loom-shell-user-controls-style';style.textContent=`
      .loom-global-controls{display:inline-flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;vertical-align:middle}
      .loom-global-control-slot{display:inline-flex;align-items:center;justify-content:center;min-width:0}
      .loom-global-control{box-sizing:border-box;min-height:36px;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:7px;padding:8px 11px!important;border:1px solid #d5e4d8!important;border-radius:11px!important;background:linear-gradient(180deg,#fff,#f6faf7)!important;color:#244d31!important;text-decoration:none!important;font:900 10px/1 Inter,system-ui!important;white-space:nowrap;cursor:pointer;box-shadow:0 4px 15px rgba(22,74,38,.045);transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease}
      .loom-global-control:hover{transform:translateY(-1px);border-color:#bcd7c3!important;box-shadow:0 8px 20px rgba(22,74,38,.08)}
      .loom-global-control .loom-global-nav-emoji,.loom-global-control .pic,.loom-global-control .loom-share-emoji,.loom-global-control .loom-profile-dock-icon{width:16px;height:16px;display:inline-grid;place-items:center;font-size:16px;line-height:1;flex:0 0 16px;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
      .loom-global-control[disabled]{opacity:.55;cursor:not-allowed;transform:none}
      @media(max-width:680px){.loom-global-controls{gap:6px}.loom-global-control{min-height:36px;padding:7px 9px!important;font-size:9px!important}}
    `;document.head.appendChild(style);
  }
  function createControlButton(def={},opts={}){
    ensureControlStyle();
    const mode=['both','emoji','text'].includes(opts.mode)?opts.mode:'both';
    const node=opts.href?document.createElement('a'):document.createElement('button');
    if(!opts.href)node.type='button';else node.href=opts.href;
    node.className=`loom-global-control ${opts.className||''}`.trim();
    node.dataset.loomGlobalControl=String(opts.control||'custom');
    node.innerHTML=buttonParts(def,mode);node.title=String(opts.title||def.label||'');
    if(typeof opts.onClick==='function')node.addEventListener('click',opts.onClick);
    return node;
  }
  async function mountUserControls(host,opts={}){
    if(!host)return {profile:null,wrapper:null};ensureControlStyle();
    let wrap=host.matches?.('[data-loom-user-controls]')?host:host.querySelector?.('[data-loom-user-controls]');
    if(!wrap){wrap=document.createElement('div');wrap.dataset.loomUserControls='1';wrap.className='loom-global-controls';if(opts.prepend)host.prepend(wrap);else host.appendChild(wrap)}
    wrap.replaceChildren();
    const settings=opts.settings||await window.LoomBrand?.fetchSettings?.(opts.apiBase||'api')||{};
    const cfg=navConfig(settings),apiBase=opts.apiBase||'api',identity=opts.identity||window.LoomIdentity?.get?.('loom-global-controls');
    const base=installRoot(apiBase),homeHref=opts.homeHref||new URL('home/',base).href;
    wrap.appendChild(createControlButton(cfg.home,{href:homeHref,mode:cfg.home.headerMode,control:'home'}));
    if(opts.shareMode==='slot'){
      const slot=document.createElement('span');slot.className='loom-global-control-slot';slot.dataset.loomShellSlot='share';if(opts.shareSlotId)slot.id=opts.shareSlotId;wrap.appendChild(slot);
    }else if(opts.share!==false&&window.LoomShare&&identity){
      await window.LoomShare.init({identity,project:opts.shareProject||opts.project||'',apiBase});
      const shareCfg=window.LoomShare.config||{emoji:'🔗',label:'Share',headerMode:'both'};
      const share=createControlButton({emoji:shareCfg.emoji||'🔗',label:shareCfg.label||'Share'},{mode:shareCfg.headerMode||'both',control:'share',className:'loom-share-button'});
      window.LoomShare.bindButton(share,{identity,project:opts.shareProject||opts.project||'',apiBase,targetUrl:opts.shareUrl||location.href,title:opts.shareTitle||document.title});wrap.appendChild(share);
    }
    if(window.LoomIdentityEntry&&opts.switchUser!==false){
      wrap.appendChild(createControlButton(cfg.switchUser,{mode:cfg.switchUser.headerMode,control:'switch-user',onClick:()=>window.LoomIdentityEntry.show?.({reloadAfterSelect:opts.reloadAfterSwitch!==false})}));
    }
    let profile=null;
    if(opts.profileMode==='slot'){
      const slot=document.createElement('span');slot.className='loom-global-control-slot';slot.dataset.loomShellSlot='profile';if(opts.profileSlotId)slot.id=opts.profileSlotId;wrap.appendChild(slot);
    }else if(opts.profile!==false&&window.LoomGlobalProfile&&identity){
      const btn=createControlButton(cfg.profile,{mode:cfg.profile.headerMode,control:'profile'});wrap.appendChild(btn);
      profile=window.LoomGlobalProfile.create({apiBase,identity,projectsProvider:opts.projectsProvider||null});profile.bindButton(btn);
    }
    return {profile,wrapper:wrap,navigation:cfg};
  }
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
  function renderAdminLinks(host,{apiBase='api',project='',target='_self',withLabel=false,status=null,excludeCurrent=true,excludeLabels=[]}={}){
    if(!host)return null;ensureAdminStyle();host.replaceChildren();let links=adminLinksForStatus({apiBase,project,status});
    const excluded=(Array.isArray(excludeLabels)?excludeLabels:[]).map(x=>String(x||'').toLowerCase()).filter(Boolean);
    if(excluded.length)links=links.filter(item=>!excluded.some(label=>String(item.label||'').toLowerCase().includes(label)));
    if(excludeCurrent){
      let current='';try{current=new URL(location.href).pathname.replace(/\/+$/,'/') }catch{}
      links=links.filter(item=>{try{return new URL(item.href,location.href).pathname.replace(/\/+$/,'/')!==current}catch{return true}});
    }
    if(withLabel&&links.length){const label=document.createElement('span');label.className='loom-shell-admin-label';label.textContent='ADMIN-ONLY TOOLS';host.appendChild(label)}
    for(const item of links){const a=document.createElement('a');a.href=item.href;a.textContent=item.label;if(target)a.target=target;if(target==='_blank')a.rel='noopener';host.appendChild(a)}return links;
  }
  function adminStatusCacheKey(identity,project=''){return `loom:admin-status:${identity?.clientId||'anon'}:${identity?.userId||'guest'}:${String(project||'global')}`}
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
    if(cached?.value&&Date.now()-Number(cached.storedAt||0)<30000){fetchAdminStatus(apiBase,identity,project).catch(()=>{});return cached.value}
    const fresh=await fetchAdminStatus(apiBase,identity,project);
    if((fresh?.isAdmin||fresh?.projectRole!=='member')||!cached?.value)return fresh;
    return cached.value;
  }
  function removeExistingAdminChrome(){document.querySelectorAll('[data-loom-admin-drawer="1"]').forEach(x=>x.remove())}
  function mountAdminTools({apiBase='api',project='',target='_self',settings=null,host=null,status=null,excludeCurrent=true,excludeLabels=[]}={}){
    ensureAdminStyle();removeExistingAdminChrome();const cfg=navConfig(settings||{});
    if(cfg.adminToolsPresentation==='top-bar'){
      if(!host)return null;host.hidden=false;host.classList.add('loom-shell-admin-zone');const links=renderAdminLinks(host,{apiBase,project,target,withLabel:true,status,excludeCurrent,excludeLabels});if(!links?.length)host.hidden=true;return host;
    }
    if(host){host.hidden=true;host.replaceChildren()}
    const wrap=document.createElement('aside');wrap.className='loom-admin-drawer';wrap.dataset.loomAdminDrawer='1';wrap.dataset.side=cfg.adminDrawerSide;wrap.dataset.open='false';
    const panel=document.createElement('div');panel.className='loom-admin-drawer-panel';const links=renderAdminLinks(panel,{apiBase,project,target,withLabel:true,status,excludeCurrent,excludeLabels});if(!links?.length)return null;
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
      if(nav){const controls=await mountUserControls(nav,{apiBase,settings,identity,homeHref,share:opts.share!==false,shareProject:opts.shareProject||'',shareUrl:opts.shareUrl||location.href,shareTitle:opts.shareTitle||document.title,profile:opts.profile!==false,projectsProvider:opts.projectsProvider||null,reloadAfterSwitch:true,prepend:true});profile=controls.profile}
      if(opts.adminTools!==false){const status=await adminStatus(apiBase,identity,opts.project||'');const canAdmin=!!status?.isAdmin||['system-owner','loom-admin','project-admin','project-manager'].includes(status?.projectRole);if(canAdmin)mountAdminTools({apiBase,project:opts.project||'',target:opts.adminTarget||'_self',settings,host:opts.adminHost||header.querySelector('[data-loom-admin-links]'),status,excludeCurrent:opts.adminExcludeCurrent!==false,excludeLabels:opts.adminExcludeLabels||[]})}
    }
    if(footer){
      await LoomBrand.mountShellFooter(footer,{apiBase,settings,navigation:cfg,homeHref});
      const fs=footer.querySelector('[data-loom-footer-share]');if(fs&&window.LoomShare&&identity)window.LoomShare.bindButton(fs,{identity,project:opts.shareProject||'',apiBase,targetUrl:opts.shareUrl||location.href,title:opts.shareTitle||document.title});const fz=footer.querySelector('[data-loom-footer-switch]');if(fz&&window.LoomIdentityEntry)fz.addEventListener('click',()=>window.LoomIdentityEntry.show?.({reloadAfterSelect:true}));const fp=footer.querySelector('[data-loom-footer-profile]');if(fp&&profile)profile.bindButton(fp);
    }
    return {settings,identity,profile,navigation:cfg};
  }

  window.LoomShell=Object.freeze({mount,mountUserControls,createControlButton,ensureControlStyle,adminStatus,refreshAdminStatus:fetchAdminStatus,canonicalAdminLinks,projectAdminQuickLinks,adminLinksForStatus,renderAdminLinks,mountAdminTools,navConfig,buttonParts});
})();
