// @loom-file release=0.15.16 revision=13 policy=package-priority
(() => {
  'use strict';

  function ensureFavicon(apiBase){
    try{
      const apiUrl=new URL(String(apiBase||'api').replace(/\/?$/,'/') ,document.baseURI||location.href);
      const href=new URL('../assets/loom-logo.png',apiUrl);
      href.searchParams.set('v',window.LoomBrand?.version||window.LoomConfig?.engineVersion||'unknown');
      let link=document.querySelector('link[data-loom-favicon="1"]');
      if(!link){link=document.createElement('link');link.rel='icon';link.type='image/png';link.dataset.loomFavicon='1';document.head.appendChild(link)}
      link.href=href.href;
    }catch{}
  }
  function installRoot(apiBase){
    try{const apiUrl=new URL(String(apiBase||'api').replace(/\/?$/,'/'),document.baseURI||location.href);return new URL('../',apiUrl)}catch{return new URL('./',location.href)}
  }
  function canonicalAdminLinks({apiBase='api',project=''}={}){
    const base=installRoot(apiBase),p=String(project||new URLSearchParams(location.search).get('project')||'').replace(/[^a-z0-9_-]/gi,'').toLowerCase();
    const q=p?`&project=${encodeURIComponent(p)}`:'';
    return [
      {label:'⚙️ LOOM Settings',href:new URL(`admin/?tab=loom${q}`,base).href},
      {label:'🎨 Project Settings',href:new URL(`admin/?tab=project${q}`,base).href},
      {label:'👥 Users',href:new URL(`admin/?tab=users${q}`,base).href},
      {label:'🪪 Identities',href:new URL(`admin/?tab=identities${q}`,base).href},
      {label:'🗄️ Database',href:new URL(`admin/?tab=database${q}`,base).href},
      {label:'📊 Activity',href:new URL(`admin/activity/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href},
      {label:'🧩 Pegboard',href:new URL(`pegboard/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href},
      {label:'🧬 Action Registry',href:new URL(`registry/${p?`?project=${encodeURIComponent(p)}`:''}`,base).href}
    ];
  }
  function ensureAdminStyle(){
    if(document.getElementById('loom-shell-admin-zone-style'))return;
    const style=document.createElement('style');style.id='loom-shell-admin-zone-style';style.textContent=`
      .loom-shell-admin-zone{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;padding:5px 7px;border:1px solid rgba(78,92,82,.16);border-radius:14px;background:rgba(255,255,255,.62)}
      .loom-shell-admin-zone .loom-shell-admin-label{font-size:8px;font-weight:950;letter-spacing:.11em;color:#718077;padding:0 3px;white-space:nowrap}
      .loom-shell-admin-zone a{white-space:nowrap}
      @media(max-width:760px){.loom-shell-admin-zone{width:100%;justify-content:center}.loom-shell-admin-zone .loom-shell-admin-label{width:100%;text-align:center}}
    `;document.head.appendChild(style);
  }
  function renderAdminLinks(host,{apiBase='api',project='',target='_self',withLabel=false}={}){
    if(!host)return null;ensureAdminStyle();host.replaceChildren();
    const links=canonicalAdminLinks({apiBase,project});
    if(withLabel){const label=document.createElement('span');label.className='loom-shell-admin-label';label.textContent='ADMIN-ONLY TOOLS';host.appendChild(label)}
    for(const item of links){const a=document.createElement('a');a.href=item.href;a.textContent=item.label;if(target)a.target=target;if(target==='_blank')a.rel='noopener';host.appendChild(a)}
    return links;
  }
  async function adminStatus(apiBase,identity){
    if(!identity?.clientId)return false;
    try{const r=await fetch(`${String(apiBase||'api').replace(/\/$/,'')}/admin.php`,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({action:'status',clientId:identity.clientId,claim:false})});const j=await r.json();return !!(r.ok&&j?.isAdmin)}catch{return false}
  }

  async function mount(opts={}){
    if(!window.LoomBrand)throw new Error('LoomBrand is required before LoomShell.');
    const apiBase=opts.apiBase||'api';
    ensureFavicon(apiBase);
    const identity=opts.identity||window.LoomIdentity?.get?.('loom-global-shell');
    const settings=opts.settings||await LoomBrand.fetchSettings(apiBase);
    window.LoomToast?.configureGlobal?.(settings);

    let profile=null;
    const header=document.getElementById(opts.headerId||'loomShellHeader');
    const footer=document.getElementById(opts.footerId||'loomShellFooter');

    if(header){
      const shellLinks=(opts.links||[]).map(x=>({...x,label:String(x?.label||'')==='LOOM Home'?'🏠 LOOM Home':x?.label}));
      await LoomBrand.mountShellHeader(header,{apiBase,settings,pageTitle:opts.pageTitle||'LOOM',links:shellLinks});
      const nav=header.querySelector('.loom-shell-chrome-links');
      if(nav && window.LoomIdentityEntry){const switchBtn=document.createElement('button');switchBtn.type='button';switchBtn.className='loom-global-profile-button';switchBtn.innerHTML='<span class="pic" aria-hidden="true">🔄</span><span>Switch User</span>';switchBtn.onclick=()=>window.LoomIdentityEntry.show?.();nav.prepend(switchBtn)}
      if(opts.profile!==false && nav && window.LoomGlobalProfile && identity){
        const btn=document.createElement('button');btn.type='button';btn.className='loom-global-profile-button';btn.innerHTML='<span class="pic" aria-hidden="true">👤</span><span>LOOM Profile</span>';nav.prepend(btn);
        profile=LoomGlobalProfile.create({apiBase,identity,projectsProvider:opts.projectsProvider||null});profile.bindButton(btn);
      }
      if(opts.adminTools!==false && nav && await adminStatus(apiBase,identity)){
        const zone=document.createElement('span');zone.className='loom-shell-admin-zone';renderAdminLinks(zone,{apiBase,project:opts.project||'',target:opts.adminTarget||'_self',withLabel:true});nav.appendChild(zone);
      }
    }
    if(footer)await LoomBrand.mountShellFooter(footer,{apiBase,settings});
    return {settings,identity,profile};
  }

  window.LoomShell=Object.freeze({mount,adminStatus,canonicalAdminLinks,renderAdminLinks});
})();
