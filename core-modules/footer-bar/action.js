// @loom-file release=0.15.13 revision=1 policy=package-priority
export async function createModule(ctx){
  let root=null,loomCubeCleanup=null;
  const desc=id=>ctx.getModuleDescriptor(id)||{};
  const clean=v=>String(v??'').trim();
  function ensureFont(url){
    if(!url||!/^https:\/\/fonts\.googleapis\.com\//i.test(url))return;
    const id='loom-footer-project-font';
    if(document.getElementById(id))return;
    const l=document.createElement('link');l.id=id;l.rel='stylesheet';l.href=url;document.head.appendChild(l);
  }
  const enumVal=(v,allowed,fallback)=>allowed.includes(v)?v:fallback;
  const px=(v,min,max,fallback)=>`${Math.max(min,Math.min(max,Number(v??fallback)||fallback))}px`;
  const bounded=(v,min,max,fallback)=>Math.max(min,Math.min(max,Number(v??fallback)||fallback));

  function styleRow(row,prefix,defaults){
    const align=enumVal(ctx.config[`${prefix}Align`],['left','center','right'],defaults.align);
    const width=enumVal(ctx.config[`${prefix}WidthMode`],['full','fit-content'],defaults.width);
    row.dataset.rowAlign=align;
    row.dataset.rowWidth=width;
    if(prefix==='brandRow'&&width==='fit-content'){
      const totalX=bounded(ctx.config.brandFitExtraWidth,0,200,100);
      const totalY=bounded(ctx.config.brandFitExtraHeight,0,200,20);
      row.dataset.fitPadding='true';
      row.style.setProperty('--brand-fit-pad-x',`${totalX/2}px`);
      row.style.setProperty('--brand-fit-pad-y',`${totalY/2}px`);
      row.style.padding='var(--brand-fit-pad-y) var(--brand-fit-pad-x)';
    }else{
      row.dataset.fitPadding='false';
      row.style.padding=`${px(ctx.config[`${prefix}PaddingY`],0,60,defaults.py)} ${px(ctx.config[`${prefix}PaddingX`],0,80,defaults.px)}`;
    }
    row.style.borderRadius=px(ctx.config[`${prefix}Radius`],0,60,defaults.radius);
    row.style.background=String(ctx.config[`${prefix}Background`]||defaults.background);
    return {align,width};
  }

  async function mountAnimatedLoomMark(host,containerSize){
    if(!host||!window.LoomBrand?.mountCube)return;
    try{
      const settings=await window.LoomBrand.fetchSettings(ctx.apiBase);
      const bc=window.LoomBrand.brandConfig(settings);
      const cubeSize=Math.max(18,Math.round(containerSize*.56));
      loomCubeCleanup?.();
      loomCubeCleanup=window.LoomBrand.mountCube(host,{
        size:cubeSize,path:bc.path,speed:bc.speed,animate:bc.animate
      });
    }catch{}
  }

  async function build(){
    const logoD=desc('core.ui.load-logo');
    const textD=desc('core.ui.load-logo-text');
    const logoCfg=logoD.config||{};
    const textCfg=textD.config||{};
    ensureFont(textCfg.fontGoogleCss);

    const logoUrl=await ctx.resolveAssetPath(logoCfg.assetPath||'assets/logo.png','project');

    root=document.createElement('section');
    root.className='loom-footer-bar';
    root.dataset.widthMode=ctx.config.widthMode==='fit-content'?'fit-content':'full';
    root.style.borderRadius=px(ctx.config.cornerRadius,0,60,30);
    root.style.background=String(ctx.config.backgroundColor||'#F7FBF8');
    root.style.setProperty('--footer-row-gap',px(ctx.config.rowGap,0,48,14));
    root.style.setProperty('--footer-padding',px(ctx.config.outerPadding,0,60,18));

    const brandRow=document.createElement('div');
    brandRow.className='loom-footer-row loom-footer-brand-row';
    styleRow(brandRow,'brandRow',{align:'center',width:'fit-content',px:18,py:16,radius:22,background:'#FFFFFF'});

    const project=document.createElement('div');
    project.className='loom-footer-project-brand';
    if(logoUrl){
      const img=document.createElement('img');
      img.className='loom-footer-project-logo';
      img.src=logoUrl;
      img.alt=logoCfg.alt||`${ctx.project} logo`;
      img.style.width=img.style.height=px(ctx.config.projectLogoSize,24,140,64);
      project.appendChild(img);
    }
    const wm=document.createElement('div');
    wm.className='loom-footer-wordmark';
    wm.style.fontFamily=`"${clean(textCfg.fontFamily||'League Spartan').replace(/["<>]/g,'')}",system-ui`;
    wm.style.fontWeight=String(textCfg.fontWeight||900);
    wm.style.fontSize=px(ctx.config.projectTextSize,9,44,17);
    const l1=document.createElement('span');
    l1.textContent=clean(textCfg.line1||ctx.project);
    l1.style.color=textCfg.greenColor||'#39A935';
    const l2=document.createElement('span');
    l2.textContent=clean(textCfg.line2||'');
    l2.style.color=textCfg.beanColor||'#A6D94E';
    wm.append(l1);
    if(l2.textContent)wm.append(l2);
    project.appendChild(wm);
    brandRow.appendChild(project);

    const toolsRow=document.createElement('div');
    toolsRow.className='loom-footer-row loom-footer-tools-row';
    toolsRow.hidden=true; // Orb Dock unhides this only when at least one tool is actually present.
    toolsRow.dataset.loomToolsRow='1';
    styleRow(toolsRow,'toolsRow',{align:'center',width:'fit-content',px:16,py:13,radius:22,background:'#F4F9F5'});
    const orbs=document.createElement('div');
    orbs.className='loom-footer-orb-slot';
    orbs.dataset.loomSlot=ctx.config.orbSlot||'orbs';
    toolsRow.appendChild(orbs);

    const loomRow=document.createElement('div');
    loomRow.className='loom-footer-row loom-footer-loom-row';
    styleRow(loomRow,'loomRow',{align:'center',width:'full',px:18,py:15,radius:22,background:'#FFFFFF'});
    const sig=document.createElement('div');
    sig.className='loom-footer-loom-signature';

    const loomContainerSize=Math.max(26,Math.min(110,Number(ctx.config.loomLogoSize||50)));
    const loomMark=document.createElement('div');
    loomMark.className='loom-footer-loom-cube';
    loomMark.style.width=loomMark.style.height=`${loomContainerSize}px`;
    sig.appendChild(loomMark);

    const copy=document.createElement('div');
    copy.className='loom-footer-loom-copy';
    copy.style.fontSize=px(ctx.config.loomTextSize,10,30,16);
    copy.innerHTML=`<strong>Powered by LOOM</strong><span>LOOM v${window.LoomConfig?.engineVersion||'unknown'} · © 2026 LOOM</span>`;
    sig.appendChild(copy);
    loomRow.appendChild(sig);

    root.append(brandRow,toolsRow,loomRow);
    ctx.mount(root,'#loom-footer-root');

    if(root.dataset.widthMode==='fit-content'){
      root.style.width='max-content';
      root.style.maxWidth='100%';
      root.style.alignSelf='center';
    }else{
      root.style.width='100%';
      root.style.maxWidth='100%';
    }

    await mountAnimatedLoomMark(loomMark,loomContainerSize);

    await ctx.log('footer-bar.mounted',{
      structure:['project-branding','more-tools','loom-attribution'],
      orbSlot:orbs.dataset.loomSlot,
      toolsWidth:toolsRow.dataset.rowWidth,
      loomMark:'animated'
    });
  }

  function cleanup(){
    loomCubeCleanup?.();loomCubeCleanup=null;
    root?.remove();root=null;
  }

  return{
    async mount(){},
    async activate(){await build();return()=>cleanup()},
    async deactivate(){cleanup()},
    async unmount(){cleanup()}
  };
}
