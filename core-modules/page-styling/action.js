// @loom-file release=0.15.16 revision=3 policy=package-priority
const DEFAULTS=Object.freeze({
  backgroundMode:'soft-gradient',backgroundColor:'#F6FAF5',backgroundHighlightColor:'#E5F8E9',backgroundEdgeColor:'#EEF5EE',
  textColor:'#122118',accentColor:'#168346',mutedColor:'#6B7D70',surfaceColor:'#FFFFFF',borderColor:'#DBE9DD',surfaceOpacity:90,
  cornerRadius:28,fontFamily:'system',fontScale:100,contentWidth:'fluid',pageGutter:18,sectionGap:24
});
const FONTS=Object.freeze({
  system:'Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
  rounded:'ui-rounded,"SF Pro Rounded","Nunito",Inter,ui-sans-serif,system-ui,sans-serif',
  serif:'ui-serif,Georgia,Cambria,"Times New Roman",serif',
  mono:'ui-monospace,"SFMono-Regular",Consolas,"Liberation Mono",monospace'
});
const WIDTHS=Object.freeze({fluid:'none',wide:'1440px',standard:'1180px',compact:'960px'});
function color(v,f){const s=String(v||'').trim();return /^#[0-9a-f]{6}$/i.test(s)?s.toUpperCase():f}
function number(v,min,max,f){const n=Number(v);return Number.isFinite(n)?Math.min(max,Math.max(min,n)):f}
function rgba(hex,alpha){const s=color(hex,'#FFFFFF').slice(1),n=parseInt(s,16);return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${alpha})`}
function config(raw={}){
  const c={...DEFAULTS,...raw};
  c.backgroundColor=color(c.backgroundColor,DEFAULTS.backgroundColor);c.backgroundHighlightColor=color(c.backgroundHighlightColor,DEFAULTS.backgroundHighlightColor);c.backgroundEdgeColor=color(c.backgroundEdgeColor,DEFAULTS.backgroundEdgeColor);
  c.textColor=color(c.textColor,DEFAULTS.textColor);c.accentColor=color(c.accentColor,DEFAULTS.accentColor);c.mutedColor=color(c.mutedColor,DEFAULTS.mutedColor);c.surfaceColor=color(c.surfaceColor,DEFAULTS.surfaceColor);c.borderColor=color(c.borderColor,DEFAULTS.borderColor);
  c.surfaceOpacity=number(c.surfaceOpacity,60,100,DEFAULTS.surfaceOpacity);c.cornerRadius=number(c.cornerRadius,0,40,DEFAULTS.cornerRadius);c.fontScale=number(c.fontScale,85,125,DEFAULTS.fontScale);c.pageGutter=number(c.pageGutter,8,48,DEFAULTS.pageGutter);c.sectionGap=number(c.sectionGap,8,48,DEFAULTS.sectionGap);
  if(!['soft-gradient','solid','transparent'].includes(c.backgroundMode))c.backgroundMode=DEFAULTS.backgroundMode;
  if(!FONTS[c.fontFamily])c.fontFamily=DEFAULTS.fontFamily;if(!WIDTHS[c.contentWidth])c.contentWidth=DEFAULTS.contentWidth;
  return c
}
function background(c){if(c.backgroundMode==='solid')return c.backgroundColor;if(c.backgroundMode==='transparent')return 'transparent';return `radial-gradient(circle at 50% -10%,${c.backgroundHighlightColor} 0,${c.backgroundColor} 45%,${c.backgroundEdgeColor} 100%)`}
export function createModule(ctx){
  let style=null,previous=new Map();
  const vars=['--loom-page-background','--loom-page-background-base','--loom-page-background-highlight','--loom-page-background-edge','--loom-page-text','--loom-page-accent','--loom-page-muted','--loom-page-surface','--loom-page-surface-translucent','--loom-page-surface-strong','--loom-page-surface-collapsed','--loom-page-border','--loom-page-radius','--loom-page-font-family','--loom-page-font-scale','--loom-page-content-max','--loom-page-gutter','--loom-page-section-gap','--loom-accent','--loom-accent-2','--loom-bg','--loom-surface','--loom-line','--green','--ink','--muted'];
  function apply(){
    const c=config(ctx.config||{}),root=document.documentElement;
    for(const key of vars)if(!previous.has(key))previous.set(key,root.style.getPropertyValue(key));
    const pairs={
      '--loom-page-background':background(c),'--loom-page-background-base':c.backgroundColor,'--loom-page-background-highlight':c.backgroundHighlightColor,'--loom-page-background-edge':c.backgroundEdgeColor,
      '--loom-page-text':c.textColor,'--loom-page-accent':c.accentColor,'--loom-page-muted':c.mutedColor,'--loom-page-surface':c.surfaceColor,'--loom-page-surface-translucent':rgba(c.surfaceColor,c.surfaceOpacity/100),'--loom-page-surface-strong':rgba(c.surfaceColor,Math.min(1,c.surfaceOpacity/100+.03)),'--loom-page-surface-collapsed':rgba(c.surfaceColor,Math.min(1,c.surfaceOpacity/100+.04)),
      '--loom-page-border':c.borderColor,'--loom-page-radius':`${c.cornerRadius}px`,'--loom-page-font-family':FONTS[c.fontFamily],'--loom-page-font-scale':String(c.fontScale/100),
      '--loom-page-content-max':WIDTHS[c.contentWidth],'--loom-page-gutter':`${c.pageGutter}px`,'--loom-page-section-gap':`${c.sectionGap}px`,'--loom-accent':c.accentColor,'--loom-accent-2':c.backgroundHighlightColor,'--loom-bg':c.backgroundColor,'--loom-surface':c.surfaceColor,'--loom-line':c.borderColor,'--green':c.accentColor,'--ink':c.textColor,'--muted':c.mutedColor
    };
    for(const [key,value] of Object.entries(pairs))root.style.setProperty(key,value);
    if(!style){style=document.createElement('style');style.dataset.loomCoreModule='loom.page.styling';document.head.appendChild(style)}
    style.textContent=`
      html,body{background:var(--loom-page-background)!important;color:var(--loom-page-text)!important}
      body{font-family:var(--loom-page-font-family)!important;font-size:calc(16px * var(--loom-page-font-scale))}
      .stage{width:100%;max-width:var(--loom-page-content-max);margin-left:auto!important;margin-right:auto!important;padding-left:var(--loom-page-gutter)!important;padding-right:var(--loom-page-gutter)!important}
      #feature-stage{gap:var(--loom-page-section-gap)!important}
      .bar{background:var(--loom-page-surface-translucent)!important;border-color:var(--loom-page-border)!important}
      .meta,.waiting{color:var(--loom-page-muted)!important}
      .loom-module-frame{border-radius:var(--loom-page-radius)!important}
      .loom-module-frame-head{background:var(--loom-page-surface-strong)!important;border-color:var(--loom-page-border)!important;color:var(--loom-page-accent)!important;border-radius:var(--loom-page-radius) var(--loom-page-radius) 0 0!important}\n      .loom-module-frame-head>span,.loom-module-frame-head>strong,.loom-module-frame-head h1,.loom-module-frame-head h2,.loom-module-frame-head h3{color:var(--loom-page-accent)!important}
      .loom-module-frame.is-collapsed .loom-module-frame-head{border-color:var(--loom-page-border)!important;background:var(--loom-page-surface-collapsed)!important;border-radius:max(8px,calc(var(--loom-page-radius) - 10px))!important}
    `;
    document.documentElement.dataset.loomPageStyling='active';
  }
  function restore(){const root=document.documentElement;for(const key of vars){const old=previous.get(key);if(old)root.style.setProperty(key,old);else root.style.removeProperty(key)}previous.clear();style?.remove();style=null;delete document.documentElement.dataset.loomPageStyling}
  return {mount(){ctx.step?.('resolve-style','completed');apply();ctx.step?.('apply-style','completed')},activate(){apply()},deactivate(){},unmount(){restore()}};
}
export default createModule;
