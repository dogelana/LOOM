// @loom-file release=0.15.10 revision=2 policy=package-priority
// Generic runtime for one dynamically generated HTML Framer module.
// Action Reader messages originate inside the sandbox and are translated into declared LOOM user actions.
export function createModule(ctx){
  let root=null,frame=null,onMessage=null;
  const clamp=(v,min,max,fallback)=>{const n=Number(v);return Number.isFinite(n)?Math.max(min,Math.min(max,n)):fallback};
  const clean=v=>String(v??'').slice(0,220);
  function safeTarget(raw){
    if(!raw||typeof raw!=='object')return null;
    return {
      tag:clean(raw.tag),id:clean(raw.id),name:clean(raw.name),
      type:clean(raw.type),role:clean(raw.role),ordinal:Number(raw.ordinal||0)||null
    };
  }
  async function handleMessage(event){
    if(!frame||event.source!==frame.contentWindow)return;
    const msg=event.data;
    if(!msg||msg.__loomFramedAction!=='v1'||String(msg.frameId||'')!==String(ctx.config.frameId||''))return;
    if(msg.eventType==='reader-ready'){
      await ctx.log('html-framer.action-reader.ready',{
        frameId:ctx.config.frameId,
        catalogCount:Number(msg.catalogCount||0),
        tracking:ctx.config.tracking
      });
      return;
    }
    const actionId=clean(msg.actionId);
    if(!actionId)return;
    const detail={
      source:'html-framer-action-reader',
      framed:true,
      frameId:clean(ctx.config.frameId),
      framedEventType:clean(msg.eventType),
      framedLabel:clean(msg.label),
      framedTarget:safeTarget(msg.target),
      x:Number.isFinite(Number(msg.x))?Number(msg.x):null,
      y:Number.isFinite(Number(msg.y))?Number(msg.y):null,
      button:Number.isFinite(Number(msg.button))?Number(msg.button):null,
      href:msg.href&&typeof msg.href==='object'?{
        host:clean(msg.href.host),path:clean(msg.href.path),external:!!msg.href.external
      }:null,
      method:clean(msg.method),
      checked:typeof msg.checked==='boolean'?msg.checked:null,
      selectedIndex:Number.isFinite(Number(msg.selectedIndex))?Number(msg.selectedIndex):null,
      fileCount:Number.isFinite(Number(msg.fileCount))?Number(msg.fileCount):null
    };
    try{
      await ctx.userAction(actionId,detail);
    }catch{
      await ctx.log('html-framer.interaction',{...detail,observedActionId:actionId});
    }
  }
  function build(){
    root=document.createElement('section');
    root.className='loom-html-framer-module';
    root.dataset.htmlFrameId=String(ctx.config.frameId||'');
    root.style.cssText='width:100%;min-width:0;overflow:hidden;background:var(--loom-page-surface,#fff);border:1px solid var(--loom-page-border,#dbe7de);box-shadow:var(--loom-shadow-1,0 8px 28px rgba(19,58,30,.07));';
    root.style.borderRadius='0 0 var(--loom-page-radius,28px) var(--loom-page-radius,28px)';

    const stage=document.createElement('div');
    stage.style.cssText='position:relative;width:100%;min-width:0;background:#fff;overflow:hidden;';
    frame=document.createElement('iframe');
    frame.src=String(ctx.config.src||'about:blank');
    frame.title=String(ctx.action.name||'HTML Frame');
    frame.loading='lazy';
    frame.referrerPolicy='no-referrer';
    frame.setAttribute('sandbox','allow-scripts allow-forms allow-modals allow-downloads');
    frame.setAttribute('allow','fullscreen');
    frame.style.cssText=`display:block;width:100%;height:${clamp(ctx.config.height,200,1600,520)}px;border:0;background:#fff;`;
    stage.appendChild(frame);
    root.appendChild(stage);
    return root;
  }
  function cleanup(){
    if(onMessage)removeEventListener('message',onMessage);
    onMessage=null;root?.remove();root=null;frame=null;
  }
  return{
    async mount(){},
    async activate(){
      if(root)return;
      ctx.step('mount-frame','active',{frameId:ctx.config.frameId});
      ctx.mount(build());
      if(ctx.config.actionReaderEnabled){
        onMessage=event=>{handleMessage(event).catch(()=>{})};
        addEventListener('message',onMessage);
      }
      ctx.step('mount-frame','completed',{frameId:ctx.config.frameId});
      await ctx.log('html-framer.frame.mounted',{
        frameId:ctx.config.frameId,entrypoint:ctx.config.entrypoint,tracking:ctx.config.tracking,
        actionReaderSummary:ctx.config.actionReaderSummary||null
      });
      return()=>cleanup();
    },
    async deactivate(){cleanup()},
    async unmount(){cleanup()}
  };
}
