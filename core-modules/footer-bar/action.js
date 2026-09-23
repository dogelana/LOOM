// @loom-file release=0.15.18 revision=4 policy=package-priority
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
    const fitKeys=prefix==='brandRow'?['brandFitExtraWidth','brandFitExtraHeight']:prefix==='loomRow'?['loomFitExtraWidth','loomFitExtraHeight']:null;
    if(width==='fit-content'&&fitKeys){
      const totalX=bounded(ctx.config[fitKeys[0]],0,200,100);
      const totalY=bounded(ctx.config[fitKeys[1]],0,200,20);
      row.dataset.fitPadding='true';
      row.style.setProperty('--row-fit-pad-x',`${totalX/2}px`);
      row.style.setProperty('--row-fit-pad-y',`${totalY/2}px`);
      row.style.padding='var(--row-fit-pad-y) var(--row-fit-pad-x)';
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
    const logoCfg=logoD.config||{};
    const wordmarkMode=enumVal(ctx.config.wordmarkMode,['project','custom','hidden'],'project');
    const colorMode=enumVal(ctx.config.wordmarkColorMode,['project','custom'],'project');
    const fontMode=enumVal(ctx.config.wordmarkFontMode,['project','custom'],'project');
    const canonicalLine1=clean(ctx.config.projectWordmarkLine1||ctx.config.projectName||'Project');
    const canonicalLine2=clean(ctx.config.projectWordmarkLine2||'');
    const line1=wordmarkMode==='custom'?clean(ctx.config.footerLine1||canonicalLine1):canonicalLine1;
    const line2=wordmarkMode==='custom'?clean(ctx.config.footerLine2||''):canonicalLine2;
    const color1=colorMode==='custom'?String(ctx.config.footerColor1||'#111111'):String(ctx.config.projectWordmarkPrimary||'#111111');
    const color2=colorMode==='custom'?String(ctx.config.footerColor2||'#168346'):String(ctx.config.projectWordmarkAccent||'#168346');
    const fontFamily=fontMode==='custom'?clean(ctx.config.footerFontFamily||'League Spartan'):clean(ctx.config.projectWordmarkFontFamily||'League Spartan');
    const fontWeight=fontMode==='custom'?bounded(ctx.config.footerFontWeight,100,950,900):bounded(ctx.config.projectWordmarkFontWeight,100,950,900);
    const fontCss=fontMode==='custom'?(fontFamily==='League Spartan'?'https://fonts.googleapis.com/css2?family=League+Spartan:wght@700;800;900&display=swap':''):String(ctx.config.projectWordmarkFontCss||'');
    ensureFont(fontCss);

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
      img.alt=logoCfg.alt||`${ctx.config.projectName||'Project'} logo`;
      img.style.width=img.style.height=px(ctx.config.projectLogoSize,24,140,64);
      project.appendChild(img);
    }
    if(wordmarkMode!=='hidden'){
      const wm=document.createElement('div');
      wm.className='loom-footer-wordmark';
      wm.style.fontFamily=`"${fontFamily.replace(/["<>]/g,'')}",system-ui`;
      wm.style.fontWeight=String(fontWeight);
      wm.style.fontSize=px(ctx.config.projectTextSize,9,44,17);
      const l1=document.createElement('span');l1.textContent=line1;l1.style.color=color1;
      const l2=document.createElement('span');l2.textContent=line2;l2.style.color=color2;
      if(l1.textContent)wm.append(l1);if(l2.textContent)wm.append(l2);
      if(wm.childElementCount)project.appendChild(wm);
    }
    brandRow.appendChild(project);
    if(!project.childElementCount)brandRow.hidden=true;

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
    styleRow(loomRow,'loomRow',{align:'center',width:'fit-content',px:18,py:15,radius:22,background:'#FFFFFF'});
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
    copy.style.color=String(ctx.config.loomTextColor||'#455A4B');
    copy.style.setProperty('--loom-footer-strong',String(ctx.config.loomStrongColor||'#173C24'));
    copy.style.setProperty('--loom-footer-meta',String(ctx.config.loomMetaColor||'#728078'));
    copy.innerHTML=`<strong>Powered by LOOM</strong><span>LOOM v${window.LoomConfig?.engineVersion||'unknown'} · © 2026 LOOM</span>`;
    sig.appendChild(copy);
    loomRow.appendChild(sig);

    // Permanent global Home/Profile controls live under the Powered by LOOM badge.
    // They resolve from the same global Navigation Chrome settings used by LOOM pages,
    // with footer-specific display modes (emoji-only by default).
    const nav=document.createElement('div');nav.className='loom-footer-global-nav';
    try{
      const gs=await window.LoomBrand?.fetchSettings?.(ctx.apiBase),nc=gs?.settings?.['loom.navigation.chrome']||{};
      const mode=(v,f)=>['both','emoji','text'].includes(v)?v:f;
      const render=(emoji,label,m)=>{const e=document.createElement('span');e.className='loom-footer-global-emoji';e.setAttribute('aria-hidden','true');e.textContent=emoji;const t=document.createElement('span');t.textContent=label;if(m==='emoji'){t.className='loom-footer-sr'}else if(m==='text'){e.hidden=true}return[e,t]};
      const home={emoji:String(nc.homeEmoji||'🏠'),label:String(nc.homeLabel||'LOOM Home'),m:mode(nc.homeFooterMode,'emoji')};
      const profile={emoji:String(nc.profileEmoji||'👤'),label:String(nc.profileLabel||'LOOM Profile'),m:mode(nc.profileFooterMode,'emoji')};
      const apiUrl=new URL(String(ctx.apiBase||'api').replace(/\/?$/,'/'),document.baseURI||location.href),homeHref=new URL('../home/',apiUrl).href;
      const a=document.createElement('a');a.href=homeHref;a.className='loom-footer-global-button';a.title=home.label;a.append(...render(home.emoji,home.label,home.m));
      const b=document.createElement('button');b.type='button';b.className='loom-footer-global-button';b.title=profile.label;b.append(...render(profile.emoji,profile.label,profile.m));b.addEventListener('click',()=>document.querySelector('[data-profile-dock-button]')?.click());
      nav.append(a,b);loomRow.appendChild(nav);
    }catch{}

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
      loomMark:'animated',wordmarkMode,colorMode,fontMode,canonicalProjectName:ctx.config.projectName||ctx.project
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
