// Generic runtime for one dynamically generated HTML Framer module.
export function createModule(ctx){
  let root=null,frame=null;
  const clamp=(v,min,max,fallback)=>{const n=Number(v);return Number.isFinite(n)?Math.max(min,Math.min(max,n)):fallback};
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
  return{
    async mount(){},
    async activate(){
      if(root)return;
      ctx.step('mount-frame','active',{frameId:ctx.config.frameId});
      ctx.mount(build());
      ctx.step('mount-frame','completed',{frameId:ctx.config.frameId});
      await ctx.log('html-framer.frame.mounted',{frameId:ctx.config.frameId,entrypoint:ctx.config.entrypoint,tracking:'boundary-only'});
      return()=>{root?.remove();root=null;frame=null};
    },
    async deactivate(){root?.remove();root=null;frame=null},
    async unmount(){root?.remove();root=null;frame=null}
  };
}
