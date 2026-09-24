// @loom-file release=0.15.38 revision=6 policy=package-priority
// Generic runtime for one dynamically generated HTML Framer module.
// Frames auto-fit delivered document height by default. Fixed-height scrolling is explicit Admin opt-in.
export function createModule(ctx){
  let root=null,surface=null,frame=null,onMessage=null,mobileMedia=null,onMobileChange=null;
  const autoHeight={desktop:null,mobile:null};
  const clamp=(v,min,max,fallback)=>{const n=Number(v);return Number.isFinite(n)?Math.max(min,Math.min(max,n)):fallback};
  const clean=v=>String(v??'').slice(0,220);
  const mode=v=>String(v||'auto').toLowerCase()==='fixed'?'fixed':'auto';
  const isMobile=()=>!!mobileMedia?.matches;
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
  function applyAutoHeight(raw){
    if(!frame)return;
    const p=profile();if(p.heightMode!=='auto')return;
    const h=clamp(Math.ceil(Number(raw)||0),120,250000,240);
    if(Math.abs((autoHeight[p.key]||0)-h)<2)return;
    autoHeight[p.key]=h;frame.style.height=`${h}px`;
  }
  async function handleMessage(event){
    if(!frame||event.source!==frame.contentWindow)return;
    const msg=event.data;if(!msg||typeof msg!=='object'||String(msg.frameId||'')!==String(ctx.config.frameId||''))return;
    if(msg.__loomFramedLayout==='v1'){
      applyAutoHeight(msg.height);
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
    root=document.createElement('section');root.className='loom-html-framer-module';root.dataset.htmlFrameId=String(ctx.config.frameId||'');root.style.cssText='width:100%;max-width:100%;min-width:0;margin:0;';
    surface=document.createElement('div');surface.className='loom-html-framer-surface';surface.style.cssText='width:100%;max-width:100%;min-width:0;margin-inline:auto;overflow:hidden;background:var(--loom-page-surface,#fff);border:1px solid var(--loom-page-border,#dbe7de);box-shadow:var(--loom-shadow-1,0 8px 28px rgba(19,58,30,.07));border-radius:var(--loom-page-radius,28px);';
    const stage=document.createElement('div');stage.style.cssText='position:relative;width:100%;min-width:0;background:#fff;overflow:hidden;';
    frame=document.createElement('iframe');frame.src=String(ctx.config.src||'about:blank');frame.title=String(ctx.action.name||'HTML Frame');frame.loading='lazy';frame.referrerPolicy='no-referrer';frame.setAttribute('sandbox','allow-scripts allow-forms allow-modals allow-downloads');frame.setAttribute('allow','fullscreen');frame.style.cssText='display:block;width:100%;height:240px;border:0;background:#fff;overflow:hidden;';
    stage.appendChild(frame);surface.appendChild(stage);root.appendChild(surface);return root;
  }
  function fitToPageLane(){
    if(!root||!surface)return;const p=profile(),widthPercent=p.widthPercent;root.dataset.frameWidthPercent=String(widthPercent);root.dataset.frameDevice=p.key;root.dataset.frameHeightMode=p.heightMode;
    const shell=root.closest('.loom-module-frame');
    if(shell){
      const head=shell.querySelector(':scope>.loom-module-frame-head'),body=shell.querySelector(':scope>.loom-module-frame-body');
      for(const node of [head,body])if(node){node.style.width=`${widthPercent}%`;node.style.maxWidth='100%';node.style.marginInline='auto'}
      root.style.width='100%';root.style.maxWidth='100%';root.style.marginInline='0';surface.style.width='100%';surface.style.maxWidth='100%';surface.style.marginInline='0';surface.style.borderRadius='0 0 var(--loom-page-radius,28px) var(--loom-page-radius,28px)';
    }else{surface.style.width=`${widthPercent}%`;surface.style.maxWidth='100%';surface.style.marginInline='auto';surface.style.borderRadius='var(--loom-page-radius,28px)'}
  }
  function applyResponsiveLayout(){
    if(!frame)return;const p=profile();fitToPageLane();
    if(p.heightMode==='fixed'){
      frame.setAttribute('scrolling','auto');frame.style.overflow='auto';frame.style.height=`${p.fixedHeight}px`;
    }else{
      frame.setAttribute('scrolling','no');frame.style.overflow='hidden';frame.style.height=`${autoHeight[p.key]||240}px`;
    }
  }
  function cleanup(){
    if(onMessage)removeEventListener('message',onMessage);onMessage=null;
    if(mobileMedia&&onMobileChange){try{mobileMedia.removeEventListener('change',onMobileChange)}catch{mobileMedia.removeListener?.(onMobileChange)}}
    mobileMedia=null;onMobileChange=null;root?.remove();root=null;surface=null;frame=null;
  }
  return{
    async mount(){},
    async activate(){
      if(root)return;ctx.step('mount-frame','active',{frameId:ctx.config.frameId});ctx.mount(build());
      mobileMedia=matchMedia('(max-width: 760px)');onMobileChange=()=>applyResponsiveLayout();try{mobileMedia.addEventListener('change',onMobileChange)}catch{mobileMedia.addListener?.(onMobileChange)}
      onMessage=event=>{handleMessage(event).catch(()=>{})};addEventListener('message',onMessage);queueMicrotask(()=>applyResponsiveLayout());
      ctx.step('mount-frame','completed',{frameId:ctx.config.frameId});
      const p=profile();await ctx.log('html-framer.frame.mounted',{frameId:ctx.config.frameId,entrypoint:ctx.config.entrypoint,tracking:ctx.config.tracking,heightMode:p.heightMode,widthPercent:p.widthPercent,widthScope:'page',responsive:true,actionReaderSummary:ctx.config.actionReaderSummary||null});
      return()=>cleanup();
    },async deactivate(){cleanup()},async unmount(){cleanup()}
  };
}
