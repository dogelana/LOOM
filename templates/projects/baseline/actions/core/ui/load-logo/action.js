// @loom-file release=0.15.17 revision=4 policy=package-priority
export async function createModule(ctx) {
  let logoWrap = null;
  let host = null;
  let mounted = false;
  let stage = null;
  let shine = null;
  let pointerCleanup = null;
  const assetPath = ctx.config.assetPath || 'assets/logo.png';

  function bindShine(url) {
    if (!stage || !shine || ctx.config.shineEnabled === false) return;
    const safeUrl = String(url || '').replace(/["\\]/g, '\\$&');
    shine.style.setProperty('--logo-mask', `url("${safeUrl}")`);
    const move = event => {
      const rect = stage.getBoundingClientRect();
      if (!rect.width || !rect.height) return;
      const x = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
      const y = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));
      stage.style.setProperty('--shine-x', `${x}%`);
      stage.style.setProperty('--shine-y', `${y}%`);
      stage.classList.add('is-shining');
    };
    const enter = event => move(event);
    const leave = () => stage.classList.remove('is-shining');
    stage.addEventListener('pointerenter', enter, { passive:true });
    stage.addEventListener('pointermove', move, { passive:true });
    stage.addEventListener('pointerleave', leave, { passive:true });
    pointerCleanup = () => {
      stage?.removeEventListener('pointerenter', enter);
      stage?.removeEventListener('pointermove', move);
      stage?.removeEventListener('pointerleave', leave);
    };
  }

  const removeLogo = async (reason = 'deactivate') => {
    if (!mounted && !logoWrap) return;
    pointerCleanup?.(); pointerCleanup = null;
    logoWrap?.remove();
    if(host){
      delete host.dataset.loomLogoHost;
      ['--loom-logo-desktop-w','--loom-logo-desktop-h','--loom-logo-mobile-w','--loom-logo-mobile-h']
        .forEach(name=>host.style.removeProperty(name));
      host.style.flexBasis='';host.style.width='';host.style.height='';
    }
    logoWrap = null;stage = null;shine = null;host = null;
    mounted = false;
    await ctx.log('logo.unmounted', { reason, assetPath });
  };

  return {
    async mount() {},
    async activate() {
      ctx.step('resolve-asset', 'active', { assetPath });
      let url = await ctx.resolveAssetPath(assetPath, ctx.config.assetScope || 'project');
      let usingLoomDefault=false;if(!url){url=new URL('../../../assets/loom-logo.png',location.href).href;usingLoomDefault=true;}
      ctx.step('resolve-asset','completed',{resolvedUrl:url,serverResolved:!usingLoomDefault,usingLoomDefault,assetPath});

      ctx.step('create-element', 'active');
      logoWrap = document.createElement('div');
      logoWrap.className = 'gb-logo-module';
      logoWrap.dataset.assetPath = assetPath;

      if (url) {
        stage = document.createElement('div');
        stage.className = 'gb-logo-shine-stage';
        const img = document.createElement('img');
        img.src = url;
        img.alt = ctx.config.alt || 'Project logo';
        const scale=Math.max(10,Math.min(100,Number(ctx.config.scalePercent??50)));
        logoWrap.dataset.scalePercent=String(scale);
        img.style.maxWidth='100%';img.style.maxHeight='100%';
        img.addEventListener('error', () => {
          stage?.remove();
          logoWrap.textContent = ctx.config.fallbackMark || '🌱';
          logoWrap.title = `Explicit asset ${assetPath} failed to render.`;
        });
        shine = document.createElement('span');
        shine.className = 'gb-logo-shine';
        shine.setAttribute('aria-hidden','true');
        stage.append(img, shine);
        logoWrap.appendChild(stage);
      } else {
        logoWrap.textContent = ctx.config.fallbackMark || '🌱';
        logoWrap.title = `Explicit asset ${assetPath} was not found.`;
      }
      ctx.step('create-element', 'completed', { assetPath });

      ctx.step('mount-logo', 'active');
      host = ctx.mount(logoWrap, ctx.config.mountSelector || '#feature-stage');
      const scale=Math.max(10,Math.min(100,Number(ctx.config.scalePercent??50)));
      const factor=scale/50; // normalized: 50 = legacy 100%; 100 = legacy 200%.
      if(host){
        host.dataset.loomLogoHost='1';
        host.style.setProperty('--loom-logo-desktop-w',`${Math.round(92*factor)}px`);
        host.style.setProperty('--loom-logo-desktop-h',`${Math.round(98*factor)}px`);
        host.style.setProperty('--loom-logo-mobile-w',`${Math.round(68*factor)}px`);
        host.style.setProperty('--loom-logo-mobile-h',`${Math.round(74*factor)}px`);
      }
      mounted = true;
      ctx.step('mount-logo', 'completed', {
        assetPath,
        region: host?.closest?.('[data-loom-region]')?.dataset?.loomRegion || null,
        slot: host?.dataset?.loomSlot || null
      });

      ctx.step('bind-shine', 'active', { enabled:ctx.config.shineEnabled !== false });
      if (url && stage && shine) bindShine(url);
      ctx.step('bind-shine', 'completed', { enabled:ctx.config.shineEnabled !== false, alphaMasked:true });

      await ctx.log('logo.mounted', {
        assetPath,
        resolvedUrl:url,
        explicitPath:!usingLoomDefault,
        usingLoomDefault,
        shineEnabled:ctx.config.shineEnabled !== false,
        shineMask:'image-alpha',
        presentation:ctx.presentation
      });
      return () => removeLogo('cleanup');
    },
    async deactivate(detail = {}) { await removeLogo(detail.reason || 'deactivate'); },
    async unmount(detail = {}) { await removeLogo(detail.reason || 'unmount'); }
  };
}
