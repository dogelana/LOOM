// @loom-file release=0.15.42 revision=10 policy=package-priority
// Generic runtime for one dynamically generated HTML Framer module.
// Frames auto-fit delivered document height by default. Fixed-height scrolling is explicit Admin opt-in.
// Optional full-page takeover portals the frame above LOOM chrome and restores it losslessly on exit.
export function createModule(ctx){
  let root=null,surface=null,stage=null,frame=null,fullscreenDock=null,fullscreenButton=null;
  let onMessage=null,mobileMedia=null,onMobileChange=null,onKeyDown=null,onViewportChange=null,onFrameLoad=null;
  let fullscreen=false,placeholder=null,scrollX=0,scrollY=0,documentStyleSnapshot=null;
  let autoFullscreenTimer=null;
  const autoHeight={desktop:null,mobile:null};
  const autoLayoutKind={desktop:null,mobile:null};
  const heightCandidate={desktop:null,mobile:null};
  const heightTimer={desktop:null,mobile:null};
  const clamp=(v,min,max,fallback)=>{const n=Number(v);return Number.isFinite(n)?Math.max(min,Math.min(max,n)):fallback};
  const clean=v=>String(v??'').slice(0,220);
  const mode=v=>String(v||'auto').toLowerCase()==='fixed'?'fixed':'auto';
  const isMobile=()=>!!mobileMedia?.matches;
  const fullscreenAllowed=()=>ctx.config.fullscreenEnabled!==false;
  const autoFullscreenOnLoad=()=>ctx.config.autoFullscreenOnLoad===true;
  const viewportHeight=()=>Math.max(1,Math.round(window.visualViewport?.height||window.innerHeight||document.documentElement.clientHeight||720));
  function profile(){
    const mobile=isMobile();
    return {
      key:mobile?'mobile':'desktop',mobile,
      heightMode:mode(mobile?ctx.config.mobileHeightMode:ctx.config.heightMode),
      fixedHeight:clamp(mobile?ctx.config.mobileHeight:ctx.config.height,200,2400,520),
      widthPercent:clamp(mobile?ctx.config.mobileWidthPercent:ctx.config.widthPercent,50,100,100)
    };
  }
  function safeTarget(raw){
    if(!raw||typeof raw!=='object')return null;
    return {tag:clean(raw.tag),id:clean(raw.id),name:clean(raw.name),type:clean(raw.type),role:clean(raw.role),ordinal:Number(raw.ordinal||0)||null};
  }
  function updateFullscreenControl(){
    if(!fullscreenDock||!fullscreenButton)return;
    fullscreenDock.style.position=fullscreen?'fixed':'absolute';
    fullscreenDock.style.top=fullscreen?'max(12px, env(safe-area-inset-top))':'12px';
    fullscreenDock.style.right=fullscreen?'max(12px, env(safe-area-inset-right))':'12px';
    fullscreenDock.style.zIndex=fullscreen?'2147483646':'30';
    fullscreenButton.setAttribute('aria-pressed',fullscreen?'true':'false');
    fullscreenButton.setAttribute('aria-label',fullscreen?'Exit HTML frame full screen':'Open HTML frame full screen');
    fullscreenButton.title=fullscreen?'Return to LOOM':'Use this HTML frame as a full page';
    const icon=fullscreen?'↙':'⛶',label=fullscreen?'Exit full screen':'Full screen';
    fullscreenButton.replaceChildren();
    const iconNode=document.createElement('span');iconNode.textContent=icon;iconNode.setAttribute('aria-hidden','true');iconNode.style.cssText='font-size:16px;line-height:1;';
    const labelNode=document.createElement('span');labelNode.textContent=label;labelNode.style.cssText='white-space:nowrap;';
    fullscreenButton.append(iconNode,labelNode);
  }
  function buildFullscreenControl(){
    if(!fullscreenAllowed())return null;
    fullscreenDock=document.createElement('div');fullscreenDock.className='loom-html-framer-fullscreen-dock';fullscreenDock.style.cssText='position:absolute;top:12px;right:12px;z-index:30;display:flex;align-items:center;pointer-events:none;';
    fullscreenButton=document.createElement('button');fullscreenButton.type='button';fullscreenButton.className='loom-html-framer-fullscreen-toggle';fullscreenButton.style.cssText='pointer-events:auto;display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px;padding:7px 12px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(18,30,22,.88);color:#fff;font:800 12px/1 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;letter-spacing:.01em;box-shadow:0 7px 24px rgba(0,0,0,.2);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);cursor:pointer;transition:transform .16s ease,background .16s ease,box-shadow .16s ease;';
    fullscreenButton.onmouseenter=()=>{fullscreenButton.style.transform='translateY(-1px)';fullscreenButton.style.background='rgba(10,24,15,.96)';fullscreenButton.style.boxShadow='0 10px 30px rgba(0,0,0,.26)'};
    fullscreenButton.onmouseleave=()=>{fullscreenButton.style.transform='';fullscreenButton.style.background='rgba(18,30,22,.88)';fullscreenButton.style.boxShadow='0 7px 24px rgba(0,0,0,.2)'};
    fullscreenButton.onfocus=()=>{fullscreenButton.style.outline='3px solid color-mix(in srgb,var(--loom-accent,#20a05a) 45%,white)';fullscreenButton.style.outlineOffset='2px'};
    fullscreenButton.onblur=()=>{fullscreenButton.style.outline=''};
    fullscreenButton.onclick=()=>setFullscreen(!fullscreen,'control');
    fullscreenDock.appendChild(fullscreenButton);updateFullscreenControl();return fullscreenDock;
  }
  function requestMeasure(reason='parent'){
    if(!frame?.contentWindow)return;
    const p=profile();
    try{frame.contentWindow.postMessage({__loomFramedLayoutRequest:'measure',frameId:String(ctx.config.frameId||''),device:p.key,heightMode:p.heightMode,reason},'*')}catch{}
  }
  function scheduleMeasure(reason='parent'){
    requestAnimationFrame(()=>requestAnimationFrame(()=>requestMeasure(reason)));
    setTimeout(()=>requestMeasure(reason),90);
    setTimeout(()=>requestMeasure(reason),320);
  }
  function commitAutoHeight(key,h,layoutKind='document'){
    if(!frame)return;
    autoLayoutKind[key]=layoutKind;
    const intrinsic=layoutKind==='viewport'?Math.max(360,viewportHeight()):h;
    autoHeight[key]=intrinsic;heightCandidate[key]=null;
    if(heightTimer[key]){clearTimeout(heightTimer[key]);heightTimer[key]=null}
    root.dataset.frameAutoLayout=layoutKind;
    const target=fullscreen?Math.max(intrinsic,viewportHeight()):intrinsic;
    const current=Math.round(parseFloat(frame.style.height)||0);
    if(Math.abs(current-target)<6)return;
    frame.style.height=`${target}px`;
  }
  function applyAutoHeight(raw,reportedKind='document'){
    if(!frame)return;
    const p=profile();if(p.heightMode!=='auto')return;
    const incomingKind=reportedKind==='viewport'?'viewport':'document';
    // Lock the intrinsic layout family for this device profile after first detection. A foreign app
    // must not flip between viewport-app and document-flow semantics while its own UI animates.
    const layoutKind=autoLayoutKind[p.key]||incomingKind;
    const h=layoutKind==='viewport'?Math.max(360,viewportHeight()):clamp(Math.ceil(Number(raw)||0),120,250000,Math.max(360,viewportHeight()));
    const current=autoHeight[p.key];
    if(current==null){commitAutoHeight(p.key,h,layoutKind);return}
    if(Math.abs(h-current)<8)return;
    if(layoutKind==='viewport'){commitAutoHeight(p.key,h,layoutKind);return}
    // Document-flow height changes must settle before they are allowed to move neighboring LOOM modules.
    // This intentionally treats growth and shrinkage the same so a busy foreign app cannot pulse the shell.
    const prior=heightCandidate[p.key];
    if(prior!=null&&Math.abs(prior-h)<8){commitAutoHeight(p.key,h,layoutKind);return}
    heightCandidate[p.key]=h;
    if(heightTimer[p.key])clearTimeout(heightTimer[p.key]);
    heightTimer[p.key]=setTimeout(()=>{heightTimer[p.key]=null;requestMeasure('confirm-settled-height')},180);
  }
  function restoreDocumentScrollLock(){
    if(!documentStyleSnapshot)return;
    const b=document.body,de=document.documentElement;
    if(b){b.style.overflow=documentStyleSnapshot.bodyOverflow;b.style.overscrollBehavior=documentStyleSnapshot.bodyOverscroll}
    if(de){de.style.overflow=documentStyleSnapshot.htmlOverflow;de.style.overscrollBehavior=documentStyleSnapshot.htmlOverscroll}
    documentStyleSnapshot=null;
  }
  function lockDocumentScroll(){
    const b=document.body,de=document.documentElement;
    documentStyleSnapshot={bodyOverflow:b?.style.overflow||'',bodyOverscroll:b?.style.overscrollBehavior||'',htmlOverflow:de?.style.overflow||'',htmlOverscroll:de?.style.overscrollBehavior||''};
    if(b){b.style.overflow='hidden';b.style.overscrollBehavior='none'}
    if(de){de.style.overflow='hidden';de.style.overscrollBehavior='none'}
  }
  function applyFullscreenShell(){
    if(!root||!surface||!stage||!frame)return;
    const p=profile(),vh=viewportHeight();
    root.dataset.frameFullscreen='true';
    root.style.cssText='position:fixed;inset:0;z-index:2147483000;width:100vw;max-width:none;height:100vh;min-height:100vh;margin:0;padding:0;background:var(--loom-page-bg,#fff);overflow-x:hidden;overscroll-behavior:contain;';root.style.height=`${vh}px`;root.style.minHeight=`${vh}px`;
    root.style.overflowY=p.heightMode==='fixed'?'hidden':'auto';
    surface.style.cssText='position:relative;width:100%;max-width:none;min-width:0;min-height:100vh;margin:0;overflow:visible;background:#fff;border:0;box-shadow:none;border-radius:0;';surface.style.minHeight=`${vh}px`;
    stage.style.cssText='position:relative;width:100%;min-width:0;min-height:100vh;background:#fff;overflow:visible;';stage.style.minHeight=`${vh}px`;
    frame.style.width='100%';frame.style.maxWidth='none';frame.style.minHeight=`${vh}px`;
    if(p.heightMode==='fixed'){
      frame.setAttribute('scrolling','auto');frame.style.overflow='auto';frame.style.height=`${vh}px`;
      surface.style.height=`${vh}px`;stage.style.height=`${vh}px`;
    }else{
      frame.setAttribute('scrolling','no');frame.style.overflow='hidden';const fullHeight=autoLayoutKind[p.key]==='viewport'?vh:Math.max(autoHeight[p.key]||vh,vh);frame.style.height=`${fullHeight}px`;
      surface.style.height='auto';stage.style.height='auto';
    }
    updateFullscreenControl();
  }
  function applyNormalShell(){
    if(!root||!surface||!stage||!frame)return;
    root.dataset.frameFullscreen='false';
    root.style.cssText='width:100%;max-width:100%;min-width:0;margin:0;';
    surface.style.cssText='position:relative;width:100%;max-width:100%;min-width:0;margin-inline:auto;overflow:hidden;background:var(--loom-page-surface,#fff);border:1px solid var(--loom-page-border,#dbe7de);box-shadow:var(--loom-shadow-1,0 8px 28px rgba(19,58,30,.07));border-radius:var(--loom-page-radius,28px);';
    stage.style.cssText='position:relative;width:100%;min-width:0;background:#fff;overflow:hidden;';
    frame.style.minHeight='0';frame.style.maxWidth='100%';
    updateFullscreenControl();
  }
  function setFullscreen(next,reason='api'){
    next=!!next&&fullscreenAllowed();if(!root||next===fullscreen)return;
    if(next){
      const current=window.__loomHtmlFramerFullscreenExit;if(typeof current==='function'&&current!==exitFromGlobal){try{current()}catch{}}
      scrollX=window.scrollX||0;scrollY=window.scrollY||0;
      placeholder=document.createComment(`loom-html-framer:${String(ctx.config.frameId||'')}`);
      root.parentNode?.insertBefore(placeholder,root);
      lockDocumentScroll();
      document.body.appendChild(root);fullscreen=true;window.__loomHtmlFramerFullscreenExit=exitFromGlobal;
    }else{
      fullscreen=false;
      if(placeholder?.parentNode)placeholder.parentNode.insertBefore(root,placeholder);
      placeholder?.remove();placeholder=null;
      if(window.__loomHtmlFramerFullscreenExit===exitFromGlobal)delete window.__loomHtmlFramerFullscreenExit;
      restoreDocumentScrollLock();
    }
    applyResponsiveLayout();
    if(!next)requestAnimationFrame(()=>window.scrollTo(scrollX,scrollY));
    try{Promise.resolve(ctx.log('html-framer.fullscreen.changed',{frameId:ctx.config.frameId,fullscreen:next,reason,device:profile().key})).catch(()=>{})}catch{}
  }
  function exitFromGlobal(){setFullscreen(false,'another-frame')}
  async function handleMessage(event){
    if(!frame||event.source!==frame.contentWindow)return;
    const msg=event.data;if(!msg||typeof msg!=='object'||String(msg.frameId||'')!==String(ctx.config.frameId||''))return;
    if(msg.__loomFramedFullscreen==='exit'){
      if(fullscreen)setFullscreen(false,'frame-escape');
      return;
    }
    if(msg.__loomFramedLayout==='v1'||msg.__loomFramedLayout==='v2'||msg.__loomFramedLayout==='v3'||msg.__loomFramedLayout==='v4'){
      applyAutoHeight(msg.height,msg.layoutKind);
      return;
    }
    if(msg.__loomFramedAction!=='v1'||!ctx.config.actionReaderEnabled)return;
    if(msg.eventType==='reader-ready'){
      await ctx.log('html-framer.action-reader.ready',{frameId:ctx.config.frameId,catalogCount:Number(msg.catalogCount||0),tracking:ctx.config.tracking});return;
    }
    const actionId=clean(msg.actionId);if(!actionId)return;
    const detail={source:'html-framer-action-reader',framed:true,frameId:clean(ctx.config.frameId),framedEventType:clean(msg.eventType),framedLabel:clean(msg.label),framedTarget:safeTarget(msg.target),x:Number.isFinite(Number(msg.x))?Number(msg.x):null,y:Number.isFinite(Number(msg.y))?Number(msg.y):null,button:Number.isFinite(Number(msg.button))?Number(msg.button):null,href:msg.href&&typeof msg.href==='object'?{host:clean(msg.href.host),path:clean(msg.href.path),external:!!msg.href.external}:null,method:clean(msg.method),checked:typeof msg.checked==='boolean'?msg.checked:null,selectedIndex:Number.isFinite(Number(msg.selectedIndex))?Number(msg.selectedIndex):null,fileCount:Number.isFinite(Number(msg.fileCount))?Number(msg.fileCount):null};
    try{await ctx.userAction(actionId,detail)}catch{await ctx.log('html-framer.interaction',{...detail,observedActionId:actionId})}
  }
  function build(){
    root=document.createElement('section');root.className='loom-html-framer-module';root.dataset.htmlFrameId=String(ctx.config.frameId||'');root.style.overflowAnchor='none';
    surface=document.createElement('div');surface.className='loom-html-framer-surface';
    stage=document.createElement('div');
    frame=document.createElement('iframe');frame.src=String(ctx.config.src||'about:blank');frame.title=String(ctx.action.name||'HTML Frame');frame.loading='lazy';frame.referrerPolicy='no-referrer';frame.setAttribute('sandbox','allow-scripts allow-forms allow-modals allow-downloads');frame.setAttribute('allow','fullscreen');frame.setAttribute('scrolling','no');frame.style.cssText=`display:block;width:100%;height:${Math.max(360,viewportHeight())}px;border:0;background:#fff;overflow:hidden;overflow-anchor:none;`;
    stage.appendChild(frame);surface.appendChild(stage);const dock=buildFullscreenControl();if(dock)surface.appendChild(dock);root.appendChild(surface);applyNormalShell();return root;
  }
  function fitToPageLane(){
    if(!root||!surface||fullscreen)return;
    const p=profile(),widthPercent=p.widthPercent;root.dataset.frameWidthPercent=String(widthPercent);root.dataset.frameDevice=p.key;root.dataset.frameHeightMode=p.heightMode;
    const shell=root.closest('.loom-module-frame');
    if(shell){
      const head=shell.querySelector(':scope>.loom-module-frame-head'),body=shell.querySelector(':scope>.loom-module-frame-body');
      for(const node of [head,body])if(node){node.style.width=`${widthPercent}%`;node.style.maxWidth='100%';node.style.marginInline='auto'}
      root.style.width='100%';root.style.maxWidth='100%';root.style.marginInline='0';surface.style.width='100%';surface.style.maxWidth='100%';surface.style.marginInline='0';surface.style.borderRadius='0 0 var(--loom-page-radius,28px) var(--loom-page-radius,28px)';
    }else{surface.style.width=`${widthPercent}%`;surface.style.maxWidth='100%';surface.style.marginInline='auto';surface.style.borderRadius='var(--loom-page-radius,28px)'}
  }
  function applyResponsiveLayout(){
    if(!frame)return;
    const p=profile();root.dataset.frameDevice=p.key;root.dataset.frameHeightMode=p.heightMode;
    if(fullscreen){applyFullscreenShell();return}
    applyNormalShell();fitToPageLane();
    if(p.heightMode==='fixed'){
      frame.setAttribute('scrolling','auto');frame.style.overflow='auto';frame.style.height=`${p.fixedHeight}px`;
    }else{
      frame.setAttribute('scrolling','no');frame.style.overflow='hidden';
      // Bootstrap auto-fit at a real viewport height so foreign 100vh layouts never get trapped in a tiny 240px iframe.
      const autoTarget=autoLayoutKind[p.key]==='viewport'?Math.max(360,viewportHeight()):(autoHeight[p.key]||Math.max(360,viewportHeight()));
      frame.style.height=`${autoTarget}px`;
      scheduleMeasure('responsive-layout');
    }
  }
  function cleanup(){
    if(fullscreen)setFullscreen(false,'cleanup');
    if(onMessage)removeEventListener('message',onMessage);onMessage=null;
    if(frame&&onFrameLoad)frame.removeEventListener('load',onFrameLoad);onFrameLoad=null;
    if(onKeyDown)removeEventListener('keydown',onKeyDown,true);onKeyDown=null;
    if(onViewportChange){removeEventListener('resize',onViewportChange);try{window.visualViewport?.removeEventListener('resize',onViewportChange)}catch{}}onViewportChange=null;
    if(mobileMedia&&onMobileChange){try{mobileMedia.removeEventListener('change',onMobileChange)}catch{mobileMedia.removeListener?.(onMobileChange)}}
    mobileMedia=null;onMobileChange=null;placeholder?.remove();placeholder=null;restoreDocumentScrollLock();
    if(autoFullscreenTimer){clearTimeout(autoFullscreenTimer);autoFullscreenTimer=null}
    for(const key of ['desktop','mobile'])if(heightTimer[key]){clearTimeout(heightTimer[key]);heightTimer[key]=null}
    root?.remove();root=null;surface=null;stage=null;frame=null;fullscreenDock=null;fullscreenButton=null;
  }
  return{
    async mount(){},
    async activate(){
      if(root)return;ctx.step('mount-frame','active',{frameId:ctx.config.frameId});ctx.mount(build());
      mobileMedia=matchMedia('(max-width: 760px)');onMobileChange=()=>{applyResponsiveLayout();scheduleMeasure('device-profile-change')};try{mobileMedia.addEventListener('change',onMobileChange)}catch{mobileMedia.addListener?.(onMobileChange)}
      onMessage=event=>{handleMessage(event).catch(()=>{})};addEventListener('message',onMessage);
      onFrameLoad=()=>{const p=profile();autoLayoutKind[p.key]=null;autoHeight[p.key]=null;heightCandidate[p.key]=null;scheduleMeasure('iframe-load')};frame.addEventListener('load',onFrameLoad);
      onKeyDown=event=>{if(fullscreen&&event.key==='Escape'){event.preventDefault();setFullscreen(false,'escape')}};addEventListener('keydown',onKeyDown,true);
      onViewportChange=()=>{applyResponsiveLayout();scheduleMeasure('viewport-change')};addEventListener('resize',onViewportChange,{passive:true});try{window.visualViewport?.addEventListener('resize',onViewportChange,{passive:true})}catch{}
      if(autoFullscreenOnLoad()&&fullscreenAllowed()){
        // Project-level launch takeover: exactly one selected frame can request this.
        // Delay one task so the module frame has a stable DOM anchor before it is portaled to body.
        autoFullscreenTimer=setTimeout(()=>{autoFullscreenTimer=null;if(root&&!fullscreen)setFullscreen(true,'project-auto')},0);
      }
      queueMicrotask(()=>{applyResponsiveLayout();scheduleMeasure('activate')});
      ctx.step('mount-frame','completed',{frameId:ctx.config.frameId});
      const p=profile();await ctx.log('html-framer.frame.mounted',{frameId:ctx.config.frameId,entrypoint:ctx.config.entrypoint,tracking:ctx.config.tracking,heightMode:p.heightMode,widthPercent:p.widthPercent,widthScope:'page',responsive:true,autoLayoutKind:autoLayoutKind[p.key]||null,fullscreenEnabled:fullscreenAllowed(),autoFullscreenOnLoad:autoFullscreenOnLoad(),actionReaderSummary:ctx.config.actionReaderSummary||null});
      return()=>cleanup();
    },async deactivate(){cleanup()},async unmount(){cleanup()}
  };
}
