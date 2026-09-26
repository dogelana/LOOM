// @loom-file release=0.15.49 revision=5 policy=package-priority
export async function createModule(ctx) {
  let root = null;
  let style = null;
  let containerObserver = null;
  let fitRaf = 0;
  let resizeHandler = null;
  let viewportHandler = null;
  let naturalWidths = [1, 1];
  let lastContainerWidth = -1;
  let lastLayoutKey = '';

  const line1 = String(ctx.config.line1 ?? 'PROJECT').toUpperCase();
  const line2 = String(ctx.config.line2 ?? '').toUpperCase();
  const fontFamily = String(ctx.config.fontFamily || 'League Spartan');
  const fontWeight = Number(ctx.config.fontWeight || 900);
  const preferredFontSize = Math.max(20, Math.min(144, Number(ctx.config.fontSize || 54)));
  const primaryColor = String(ctx.config.primaryColor || '#111111');
  const accentColor = String(ctx.config.accentColor || '#168346');
  const fontCssUrl = String(ctx.config.fontGoogleCss || 'https://fonts.googleapis.com/css2?family=League+Spartan:wght@700;800;900&display=swap');

  function ensureFontLink() {
    if (fontFamily !== 'League Spartan') return null;
    const id = 'loom-font-league-spartan';
    let link = document.getElementById(id);
    if (link) return link;
    link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = fontCssUrl;
    link.referrerPolicy = 'no-referrer-when-downgrade';
    document.head.appendChild(link);
    return link;
  }

  async function waitForFont() {
    ensureFontLink();
    if (!document.fonts?.load) return;
    try {
      await Promise.race([
        document.fonts.load(`${fontWeight} ${preferredFontSize}px "${fontFamily}"`, `${line1} ${line2}`),
        new Promise(resolve => setTimeout(resolve, 1800))
      ]);
    } catch {}
  }

  function injectStyle() {
    if (style) return;
    style = document.createElement('style');
    style.dataset.loomModule = ctx.action.id;
    style.textContent = `
      .loom-logo-wordmark{
        flex:0 0 auto;width:max-content;min-width:0;height:auto;
        display:flex;flex-direction:column;align-items:flex-start;justify-content:center;
        gap:0;margin:0;padding:.09em 0 .07em;box-sizing:border-box;overflow:visible;
        visibility:hidden;
      }
      .loom-logo-wordmark[data-fit-ready="1"]{visibility:visible}
      .loom-logo-wordmark-line{
        display:block;flex:0 0 auto;width:max-content;max-width:none;text-align:left;
        font-family:"${fontFamily}",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        font-size:${preferredFontSize}px;font-weight:${fontWeight};line-height:.88;
        letter-spacing:-.018em;text-transform:uppercase;white-space:nowrap;
        -webkit-font-smoothing:antialiased;text-rendering:geometricPrecision;
        transform-origin:left center;text-shadow:0 .055em .15em rgba(8,55,18,.07);
      }
      .loom-logo-wordmark-line.is-primary{color:${primaryColor}}
      .loom-logo-wordmark-line.is-accent{color:${accentColor}}
    `;
    document.head.appendChild(style);
  }

  function measureNaturalWidths() {
    const box = document.createElement('div');
    box.setAttribute('aria-hidden','true');
    Object.assign(box.style,{
      position:'fixed',left:'-10000px',top:'-10000px',visibility:'hidden',
      pointerEvents:'none',whiteSpace:'nowrap'
    });
    const make = value => {
      const n=document.createElement('span');
      n.textContent=value;
      Object.assign(n.style,{
        display:'block',width:'max-content',
        fontFamily:`"${fontFamily}",ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif`,
        fontSize:`${preferredFontSize}px`,fontWeight:String(fontWeight),
        lineHeight:'.88',letterSpacing:'-.018em',textTransform:'uppercase',whiteSpace:'nowrap'
      });
      return n;
    };
    const a=make(line1),b=make(line2);
    box.append(a,b);document.body.appendChild(box);
    naturalWidths=[
      Math.max(1,a.getBoundingClientRect().width),
      Math.max(1,b.getBoundingClientRect().width)
    ];
    box.remove();
  }

  function stableContainerWidth() {
    const header=root?.closest?.('.loom-header-bar');
    const container=header?.parentElement;
    const viewport=window.visualViewport?.width||window.innerWidth||document.documentElement.clientWidth||320;
    const containerWidth=container?.getBoundingClientRect?.().width||viewport;
    return Math.max(120,Math.min(viewport-24,containerWidth));
  }

  function availableBrandWidth() {
    const header=root?.closest?.('.loom-header-bar');
    const brand=root?.closest?.('.loom-header-brand');
    const media=header?.querySelector?.('.loom-header-brand-media');
    const utility=header?.querySelector?.('.loom-header-utility');
    const stableWidth=stableContainerWidth();
    if(!header||!brand)return Math.min(760,stableWidth);

    const hs=getComputedStyle(header),bs=getComputedStyle(brand);
    const horizontalPadding=(parseFloat(hs.paddingLeft)||0)+(parseFloat(hs.paddingRight)||0);
    const headerGap=parseFloat(hs.gap)||0;
    const brandGap=parseFloat(bs.gap)||0;
    const mediaWidth=media?.getBoundingClientRect?.().width||0;
    const utilityWidth=utility?.getBoundingClientRect?.().width||0;

    // The sizing input is the stable containing block, never the header's own
    // width, because the wordmark itself contributes to header width.
    return Math.max(110,stableWidth-horizontalPadding-mediaWidth-utilityWidth-headerGap-brandGap-12);
  }

  function applyAuthoritativeSize() {
    if(!root)return;
    const nodes=[...root.querySelectorAll('.loom-logo-wordmark-line')];
    if(nodes.length!==2)return;

    const maxNaturalWidth=Math.max(...naturalWidths,1);
    const safeWidth=availableBrandWidth();
    const emergencyScale=Math.min(1,safeWidth/maxNaturalWidth);
    const actualFontSize=Math.max(18,preferredFontSize*emergencyScale);
    const actualScale=actualFontSize/preferredFontSize;
    const targetWidth=Math.max(1,maxNaturalWidth*actualScale);
    const layoutKey=[
      Math.round(safeWidth*10)/10,
      Math.round(actualFontSize*100)/100,
      Math.round(targetWidth*10)/10
    ].join(':');

    if(layoutKey===lastLayoutKey&&root.dataset.fitReady==='1')return;
    lastLayoutKey=layoutKey;

    nodes[0].textContent=line1;
    nodes[1].textContent=line2;
    nodes.forEach((node,i)=>{
      node.style.fontSize=`${actualFontSize}px`;
      const renderedNatural=Math.max(1,naturalWidths[i]*actualScale);
      node.style.transform=`scaleX(${targetWidth/renderedNatural})`;
    });

    root.style.width=`${Math.ceil(targetWidth)}px`;
    root.style.height='auto';
    root.dataset.requestedFontSize=String(preferredFontSize);
    root.dataset.renderedFontSize=String(Math.round(actualFontSize*100)/100);
    root.dataset.fitReady='1';
  }

  function scheduleFit(){
    if(fitRaf)return;
    fitRaf=requestAnimationFrame(()=>{fitRaf=0;applyAuthoritativeSize()});
  }

  function bindStableResizeSignals(){
    const header=root?.closest?.('.loom-header-bar');
    const container=header?.parentElement;

    if(container&&window.ResizeObserver){
      lastContainerWidth=container.getBoundingClientRect().width||-1;
      containerObserver=new ResizeObserver(entries=>{
        const width=entries[0]?.contentRect?.width??container.getBoundingClientRect().width;
        if(Math.abs(width-lastContainerWidth)<.5)return;
        lastContainerWidth=width;
        scheduleFit();
      });
      containerObserver.observe(container);
    }

    resizeHandler=()=>scheduleFit();
    window.addEventListener('resize',resizeHandler,{passive:true});

    if(window.visualViewport){
      viewportHandler=()=>scheduleFit();
      window.visualViewport.addEventListener('resize',viewportHandler,{passive:true});
    }
  }

  async function removeUi(reason='deactivate'){
    if(fitRaf)cancelAnimationFrame(fitRaf);
    fitRaf=0;
    containerObserver?.disconnect();containerObserver=null;
    if(resizeHandler)window.removeEventListener('resize',resizeHandler);
    resizeHandler=null;
    if(viewportHandler&&window.visualViewport)window.visualViewport.removeEventListener('resize',viewportHandler);
    viewportHandler=null;
    root?.remove();style?.remove();root=null;style=null;
    await ctx.log('logo-text.unmounted',{reason});
  }

  return{
    async mount(){},
    async activate(){
      injectStyle();
      ctx.step('create-element','active');
      root=document.createElement('div');
      root.className='loom-logo-wordmark';
      root.setAttribute('aria-label',`${line1} ${line2}`.trim());
      const a=document.createElement('div');a.className='loom-logo-wordmark-line is-primary';
      const b=document.createElement('div');b.className='loom-logo-wordmark-line is-accent';if(!line2)b.hidden=true;
      root.append(a,b);
      ctx.step('create-element','completed',{lines:[line1,line2],fontFamily,fontSize:preferredFontSize,primaryColor,accentColor});

      ctx.step('mount-text','active');
      const host=ctx.mount(root,ctx.config.mountSelector||'#feature-stage');
      ctx.step('mount-text','completed',{
        region:host?.closest?.('[data-loom-region]')?.dataset?.loomRegion||null,
        slot:host?.dataset?.loomSlot||null
      });

      ctx.step('fit-text','active',{fontSize:preferredFontSize,fontFamily,mode:'stable-container'});
      await waitForFont();
      measureNaturalWidths();
      applyAuthoritativeSize();
      bindStableResizeSignals();
      ctx.step('fit-text','completed',{fontSize:preferredFontSize,fontFamily,mode:'stable-container'});

      await ctx.log('logo-text.mounted',{
        lines:[line1,line2],requestedFontSize:preferredFontSize,fontFamily,fontWeight,
        primaryColor,accentColor,fitMode:'stable-container-emergency-shrink-only',
        feedbackLoopProtection:true,presentation:ctx.presentation
      });
      return()=>removeUi('cleanup');
    },
    async deactivate(detail={}){await removeUi(detail.reason||'deactivate')},
    async unmount(detail={}){await removeUi(detail.reason||'unmount')}
  };
}
