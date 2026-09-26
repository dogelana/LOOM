// @loom-file release=0.15.74 revision=10 policy=package-priority
export async function createModule(ctx) {
  let root=null,style=null,progressText=null,progressBar=null,statusText=null,tipText=null,cubeCleanup=null,tipTimer=null;
  let mountedAt=0,removed=false,finishPromise=null,globalSettings=null;
  const cfg=ctx.config||{},sleep=ms=>new Promise(r=>setTimeout(r,ms));
  const tips=[
    'LOOM loads each project feature as an independent module.',
    'Your project identity can inherit or override your LOOM profile.',
    'Administrators can inspect module activity in Pegboard.',
    'Projects can add new capabilities without rewriting the LOOM core.',
    'LOOM keeps platform settings separate from project settings.',
    'Project branding stays front and center while LOOM powers the engine underneath.'
  ];
  function descriptor(id){return ctx.getModuleDescriptor?.(id)||null}
  function branding(){
    const logo=descriptor(cfg.logoActionId||'core.ui.load-logo'),text=descriptor(cfg.logoTextActionId||'core.ui.load-logo-text');
    return{
      assetPath:logo?.config?.assetPath||'assets/logo.png',assetScope:logo?.config?.assetScope||'project',alt:logo?.config?.alt||'Project logo',
      line1:String(text?.config?.line1||ctx.project).toUpperCase(),line2:String(text?.config?.line2||'').toUpperCase(),
      fontFamily:String(text?.config?.fontFamily||'system-ui'),fontWeight:Number(text?.config?.fontWeight||900),fontCss:String(text?.config?.fontGoogleCss||''),
      color1:String(text?.config?.primaryColor||'#111111'),color2:String(text?.config?.accentColor||'#168346')
    }
  }
  function ensureFont(b){if(!b.fontCss||document.getElementById('loom-loader-brand-font'))return;const l=document.createElement('link');l.id='loom-loader-brand-font';l.rel='stylesheet';l.href=b.fontCss;document.head.appendChild(l)}
  function injectStyle(b){
    style=document.createElement('style');style.dataset.loomModule=ctx.action.id;style.textContent=`
      .loom-loader-overlay{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;background:rgba(246,250,246,.97);backdrop-filter:blur(18px);font-family:Inter,ui-sans-serif,system-ui;color:#17301f;transition:opacity .25s ease,visibility .25s ease}
      .loom-loader-overlay.is-leaving{opacity:0;visibility:hidden;pointer-events:none}
      .loom-loader-card{width:min(470px,calc(100vw - 32px));display:grid;justify-items:center;gap:15px;padding:26px 24px 18px;border:1px solid rgba(43,117,63,.13);border-radius:30px;background:rgba(255,255,255,.82);box-shadow:0 34px 110px rgba(23,76,40,.12);overflow:hidden}
      .loom-loader-project-stage{position:relative;width:310px;height:228px;display:grid;place-items:center;margin:0 auto 1px}
      .loom-loader-project-core{position:relative;z-index:4;width:132px;height:132px;display:grid;place-items:center;filter:drop-shadow(0 18px 28px rgba(22,92,38,.13));animation:loomProjectFloat 3.2s ease-in-out infinite}
      .loom-loader-project-core img{display:block;max-width:100%;max-height:100%;object-fit:contain}
      .loom-loader-fallback{font-size:70px}
      .loom-loader-orbit{position:absolute;z-index:3;inset:0;animation:loomProjectOrbit 7.4s linear infinite;pointer-events:none}
      .loom-loader-orbit.second{inset:25px 35px;animation-direction:reverse;animation-duration:5.8s}
      .loom-loader-token{position:absolute;left:50%;top:3px;transform:translateX(-50%);padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.93);border:1px solid rgba(38,112,55,.12);box-shadow:0 9px 25px rgba(30,79,42,.11);font-family:"${b.fontFamily}",ui-sans-serif,system-ui;font-weight:${b.fontWeight};font-size:19px;line-height:.9;letter-spacing:-.02em;white-space:nowrap;animation:loomProjectCounterOrbit 7.4s linear infinite}
      .loom-loader-orbit.second .loom-loader-token{animation-direction:reverse;animation-duration:5.8s}.loom-loader-token.a{color:${b.color1}}.loom-loader-token.b{color:${b.color2}}
      .loom-loader-particles{position:absolute;inset:7px;z-index:1;animation:loomParticleDrift 11s linear infinite;pointer-events:none}.loom-loader-particle{position:absolute;border-radius:50%;background:${b.color1};opacity:.72;box-shadow:0 0 0 5px color-mix(in srgb,${b.color1} 10%,transparent)}.loom-loader-particle:nth-child(1){width:7px;height:7px;top:12px;left:37px}.loom-loader-particle:nth-child(2){width:9px;height:9px;right:26px;top:61px;background:${b.color2}}.loom-loader-particle:nth-child(3){width:5px;height:5px;left:52px;bottom:18px;background:${b.color2}}.loom-loader-particle:nth-child(4){width:6px;height:6px;right:67px;bottom:3px}.loom-loader-particle:nth-child(5){width:4px;height:4px;left:19px;top:115px;background:${b.color2}}
      .loom-loader-tip{min-height:30px;text-align:center;font-size:10px;line-height:1.45;color:#6b7b71;max-width:370px;padding:0 10px}
      .loom-loader-progress{width:100%;display:grid;gap:8px}.loom-loader-row{display:flex;justify-content:space-between;align-items:center;gap:12px}.loom-loader-count{font-size:12px;font-weight:950}.loom-loader-status{font-size:10px;color:#728078;max-width:235px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.loom-loader-track{height:7px;border-radius:999px;background:#e5eee7;overflow:hidden}.loom-loader-fill{height:100%;width:0;background:linear-gradient(90deg,${b.color1},${b.color2});transition:width .18s ease}
      .loom-loader-powered{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;padding-top:9px;margin-top:1px;border-top:1px solid rgba(217,231,220,.75);font-size:8px;font-weight:950;letter-spacing:.12em;text-transform:uppercase;color:#607168}.loom-loader-powered-cube{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px}.loom-loader-powered span{white-space:nowrap}
      @keyframes loomProjectOrbit{to{transform:rotate(360deg)}}@keyframes loomProjectCounterOrbit{to{transform:translateX(-50%) rotate(-360deg)}}@keyframes loomProjectFloat{0%,100%{transform:translateY(3px) scale(.98)}50%{transform:translateY(-5px) scale(1.025)}}@keyframes loomParticleDrift{to{transform:rotate(-360deg)}}
      @media(max-width:520px){.loom-loader-card{padding:22px 16px 16px}.loom-loader-project-stage{transform:scale(.9);margin:-11px 0 -9px}.loom-loader-status{max-width:155px}.loom-loader-powered-cube{width:30px;height:30px;flex-basis:30px}}
      @media(prefers-reduced-motion:reduce){.loom-loader-project-core,.loom-loader-orbit,.loom-loader-token,.loom-loader-particles{animation:none!important}}
    `;document.head.appendChild(style)
  }
  async function build(){
    const b=branding();ensureFont(b);injectStyle(b);
    try{globalSettings=await (window.LoomBrand?.fetchSettings?.('../../../api')||ctx.fetchApi('global-settings.php').then(r=>r.json()))}catch{globalSettings={settings:{}}}
    let releaseInfo=null;
    try{releaseInfo=await (window.LoomBrand?.fetchRelease?.('../../../api')||ctx.fetchApi('version.php').then(r=>r.json()))}catch{}
    const loomVersion=String(releaseInfo?.canonicalVersion||window.LoomConfig?.engineVersion||window.LoomBrand?.version||'unknown').replace(/[^0-9A-Za-z._+\-]/g,'')||'unknown';
    let url=null;try{url=await ctx.resolveAssetPath(b.assetPath,b.assetScope)}catch{}
    root=document.createElement('div');root.className='loom-loader-overlay';root.setAttribute('role','status');root.setAttribute('aria-live','polite');
    const primaryOrbit=b.line1?`<div class="loom-loader-orbit"><span class="loom-loader-token a"></span></div>`:'';
    const secondaryOrbit=b.line2?`<div class="loom-loader-orbit second"><span class="loom-loader-token b"></span></div>`:'';
    root.innerHTML=`<section class="loom-loader-card"><div class="loom-loader-project-stage"><div class="loom-loader-particles"><i class="loom-loader-particle"></i><i class="loom-loader-particle"></i><i class="loom-loader-particle"></i><i class="loom-loader-particle"></i><i class="loom-loader-particle"></i></div>${primaryOrbit}${secondaryOrbit}<div class="loom-loader-project-core"></div></div><div class="loom-loader-tip"></div><div class="loom-loader-progress"><div class="loom-loader-row"><strong class="loom-loader-count">0 / 0 modules ready</strong><span class="loom-loader-status">Preparing project modules…</span></div><div class="loom-loader-track"><div class="loom-loader-fill"></div></div></div><div class="loom-loader-powered"><div class="loom-loader-powered-cube"></div><span class="loom-loader-powered-label"></span></div></section>`;
    const tokenA=root.querySelector('.loom-loader-token.a'),tokenB=root.querySelector('.loom-loader-token.b');if(tokenA)tokenA.textContent=b.line1;if(tokenB)tokenB.textContent=b.line2;
    const core=root.querySelector('.loom-loader-project-core');if(url){const img=document.createElement('img');img.src=url;img.alt=b.alt;core.appendChild(img)}else{const f=document.createElement('span');f.className='loom-loader-fallback';f.textContent=cfg.logoFallback||'✦';core.appendChild(f)}
    const poweredLabel=root.querySelector('.loom-loader-powered-label');if(poweredLabel)poweredLabel.textContent=`Powered by LOOM · v${loomVersion}`;
    progressText=root.querySelector('.loom-loader-count');progressBar=root.querySelector('.loom-loader-fill');statusText=root.querySelector('.loom-loader-status');tipText=root.querySelector('.loom-loader-tip');document.body.appendChild(root);mountedAt=Date.now();
    const bc=window.LoomBrand?.brandConfig?.(globalSettings)||{path:'hero-orbit',speed:1,animate:true};cubeCleanup=window.LoomBrand?.mountCube?.(root.querySelector('.loom-loader-powered-cube'),{size:27,path:bc.path,speed:bc.speed,animate:bc.animate});
    const loaderFeatureEnabled=globalSettings?.moduleStates?.['loom.loader.experience']?.enabled!==false,lx=loaderFeatureEnabled?(globalSettings?.settings?.['loom.loader.experience']||{}):{showTips:'off',minimumVisibleMs:0};if(lx.showTips!=='off'){let i=Math.floor(Math.random()*tips.length);tipText.textContent=tips[i];tipTimer=setInterval(()=>{i=(i+1)%tips.length;if(tipText)tipText.textContent=tips[i]},Math.max(2000,Number(lx.tipIntervalSeconds||4)*1000))}else tipText.textContent='LOOM is assembling this project from its registered modules.';
    await ctx.log('loader.mounted',{loomVersion,motionPath:bc.path,project:ctx.project,projectBrandingPrimary:true,versionSource:releaseInfo?.canonicalVersion?'canonical-api':'engine-fallback'})
  }
  async function remove(reason='complete'){if(removed)return;removed=true;if(tipTimer)clearInterval(tipTimer);cubeCleanup?.();root?.classList.add('is-leaving');await sleep(260);root?.remove();style?.remove();await ctx.log('loader.unmounted',{reason})}
  return{
    async mount(){},
    async activate(){ctx.step('read-branding','active');await build();ctx.step('read-branding','completed');ctx.step('mount-overlay','completed');ctx.step('track-modules','active');return()=>remove('cleanup')},
    async setProgress({loaded=0,total=0,failed=0,current=null,phase='loading'}={}){if(!root)return;const done=loaded+failed,pct=total?Math.min(100,done/total*100):100;if(progressText)progressText.textContent=`${loaded} / ${total} modules ready${failed?` · ${failed} failed`:''}`;if(progressBar)progressBar.style.width=`${pct}%`;if(statusText)statusText.textContent=phase==='complete'?(failed?'Project ready with module errors.':'Project modules ready.'):current?`Preparing ${current}…`:'Preparing project modules…';root.setAttribute('aria-label',`${loaded} of ${total} modules ready`)},
    async finish({loaded=0,total=0,failed=0}={}){if(finishPromise)return finishPromise;finishPromise=(async()=>{ctx.step('track-modules','completed',{loaded,total,failed});const loaderFeatureEnabled=globalSettings?.moduleStates?.['loom.loader.experience']?.enabled!==false,lx=loaderFeatureEnabled?(globalSettings?.settings?.['loom.loader.experience']||{}):{minimumVisibleMs:0},min=Math.max(0,Number(lx.minimumVisibleMs??cfg.minimumVisibleMs??320)),wait=Math.max(0,min-(Date.now()-mountedAt));if(wait)await sleep(wait);ctx.step('dismiss-overlay','active');await remove(failed?'complete-with-errors':'complete');ctx.step('dismiss-overlay','completed')})();return finishPromise},
    async deactivate(d={}){await remove(d.reason||'deactivate')},async unmount(d={}){await remove(d.reason||'unmount')}
  }
}
