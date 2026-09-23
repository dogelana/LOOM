// @loom-file release=0.15.02 revision=10 policy=package-priority
(() => {
  'use strict';

  function ensureFavicon(apiBase){
    try{
      const apiUrl=new URL(String(apiBase||'api').replace(/\/?$/,'/') ,location.href);
      const href=new URL('../assets/loom-logo.png',apiUrl);
      href.searchParams.set('v',window.LoomBrand?.version||window.LoomConfig?.engineVersion||'unknown');

      let link=document.querySelector('link[data-loom-favicon="1"]');
      if(!link){
        link=document.createElement('link');
        link.rel='icon';link.type='image/png';link.dataset.loomFavicon='1';
        document.head.appendChild(link);
      }
      link.href=href.href;
    }catch{}
  }

  async function mount(opts={}){
    if(!window.LoomBrand)throw new Error('LoomBrand is required before LoomShell.');
    const apiBase=opts.apiBase||'api';
    ensureFavicon(apiBase);
    const identity=opts.identity||window.LoomIdentity?.get?.('loom-global-shell');
    const settings=opts.settings||await LoomBrand.fetchSettings(apiBase);

    let profile=null;
    const header=document.getElementById(opts.headerId||'loomShellHeader');
    const footer=document.getElementById(opts.footerId||'loomShellFooter');

    if(header){
      await LoomBrand.mountShellHeader(header,{
        apiBase,settings,pageTitle:opts.pageTitle||'LOOM',
        links:opts.links||[]
      });

      const nav=header.querySelector('.loom-shell-chrome-links');
      if(nav && window.LoomIdentityEntry){const switchBtn=document.createElement('button');switchBtn.type='button';switchBtn.className='loom-global-profile-button';switchBtn.innerHTML='<span class="pic" aria-hidden="true">🔄</span><span>Switch User</span>';switchBtn.onclick=()=>window.LoomIdentityEntry.show?.();nav.prepend(switchBtn);}
      if(opts.profile!==false && nav && window.LoomGlobalProfile && identity){
        const btn=document.createElement('button');
        btn.type='button';
        btn.className='loom-global-profile-button';
        btn.innerHTML='<span class="pic" aria-hidden="true">👤</span><span>LOOM Profile</span>';
        nav.prepend(btn);
        profile=LoomGlobalProfile.create({apiBase,identity,projectsProvider:opts.projectsProvider||null});
        profile.bindButton(btn);
      }
    }

    if(footer){
      await LoomBrand.mountShellFooter(footer,{apiBase,settings});
    }

    return {settings,identity,profile};
  }

  window.LoomShell=Object.freeze({mount});
})();