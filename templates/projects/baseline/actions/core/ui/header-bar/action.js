// @loom-file release=0.15.49 revision=4 policy=package-priority
export async function createModule(ctx) {
  let root = null;
  let mounted = false;

  const removeHeader = async (reason = 'deactivate') => {
    if (!root && !mounted) return;
    root?.remove();
    root = null;
    mounted = false;
    await ctx.log('header-bar.unmounted', { reason, region: ctx.presentation?.region || 'header-bar' });
  };

  function resolvedLayout(){
    const widthMode=ctx.config.widthMode==='full'?'full':'fit-content';
    const brandPosition=['left','center','right'].includes(ctx.config.brandPosition)?ctx.config.brandPosition:'left';
    return {widthMode,brandPosition};
  }

  function applyLayout(brand){
    if(!root)return;
    const {widthMode,brandPosition}=resolvedLayout();
    root.dataset.widthMode=widthMode;
    root.dataset.brandPosition=brandPosition;
    root.style.minHeight=`${Math.max(72,Number(ctx.config.barHeight||124))}px`;
    root.style.borderRadius=`${Math.max(0,Number(ctx.config.cornerRadius||30))}px`;
    root.style.background=String(ctx.config.backgroundColor||'#FFFFFF');

    // Important: apply these AFTER ctx.mount(), because LOOM presentation metadata
    // is applied during mount and older manifests could otherwise force width:100%.
    if(widthMode==='fit-content'){
      root.style.width='max-content';
      root.style.inlineSize='max-content';
      root.style.maxWidth='100%';
      root.style.flex='0 0 auto';
      root.style.alignSelf=brandPosition==='left'?'flex-start':brandPosition==='right'?'flex-end':'center';
    }else{
      root.style.width='100%';
      root.style.inlineSize='auto';
      root.style.maxWidth='100%';
      root.style.flex='0 1 auto';
      root.style.alignSelf='stretch';
    }
    if(brand){
      brand.style.justifyContent=brandPosition==='center'?'center':brandPosition==='right'?'flex-end':'flex-start';
      brand.style.flex=widthMode==='fit-content'?'0 0 auto':'1 1 auto';
    }
  }

  return {
    async mount() {},
    async activate() {
      ctx.step('create-region', 'active');
      root = document.createElement('section');
      root.className = 'loom-header-bar';
      root.setAttribute('aria-label', ctx.config.ariaLabel || 'Application header');
      ctx.step('create-region', 'completed', { region: ctx.presentation?.region || 'header-bar' });

      ctx.step('create-slots', 'active');
      const brand = document.createElement('div');
      brand.className = 'loom-header-slot loom-header-brand';
      brand.dataset.loomSlot = ctx.config.brandSlot || 'brand';
      brand.setAttribute('aria-label', 'Brand');

      const media = document.createElement('div');
      media.className = 'loom-header-brand-media';
      media.dataset.loomSlot = ctx.config.brandMediaSlot || 'brand-media';
      media.setAttribute('aria-label', 'Brand media');

      const text = document.createElement('div');
      text.className = 'loom-header-brand-text';
      text.dataset.loomSlot = ctx.config.brandTextSlot || 'brand-text';
      text.setAttribute('aria-label', 'Brand text');
      brand.append(media, text);

      const utility = document.createElement('div');
      utility.className = 'loom-header-slot loom-header-utility';
      utility.dataset.loomSlot = ctx.config.utilitySlot || 'utility';
      utility.setAttribute('aria-label', 'Header utilities');
      root.append(brand, utility);
      ctx.step('create-slots', 'completed', {slots:[brand.dataset.loomSlot,media.dataset.loomSlot,text.dataset.loomSlot,utility.dataset.loomSlot]});

      ctx.step('mount-region', 'active');
      ctx.mount(root, ctx.config.mountSelector || '#feature-stage');
      mounted = true;
      applyLayout(brand);
      requestAnimationFrame(()=>applyLayout(brand));
      ctx.step('mount-region', 'completed', {region:ctx.presentation?.region||'header-bar',widthMode:resolvedLayout().widthMode,brandPosition:resolvedLayout().brandPosition});
      await ctx.log('header-bar.mounted', {region:ctx.presentation?.region||'header-bar',widthMode:resolvedLayout().widthMode,brandPosition:resolvedLayout().brandPosition,slots:[brand.dataset.loomSlot,media.dataset.loomSlot,text.dataset.loomSlot,utility.dataset.loomSlot]});
      return () => removeHeader('cleanup');
    },
    async deactivate(detail = {}) { await removeHeader(detail.reason || 'deactivate'); },
    async unmount(detail = {}) { await removeHeader(detail.reason || 'unmount'); }
  };
}
