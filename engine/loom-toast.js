// @loom-file release=0.15.15 revision=1 policy=package-priority
(()=>{
  'use strict';
  if(window.LoomToast)return;
  const scriptUrl=(()=>{try{return new URL(document.currentScript?.src||'engine/loom-toast.js',location.href)}catch{return null}})();
  const apiBase=(()=>{try{return new URL('../api',scriptUrl||location.href).href.replace(/\/$/,'')}catch{return 'api'}})();
  const DEFAULTS={position:'bottom-right',durationMs:3600,saveDurationMs:2400,maxVisible:4,accentColor:'#173F27',surfaceColor:'#FFFFFF',textColor:'#173722'};
  let globalCfg={...DEFAULTS},projectTheme=null,container=null,tooltip=null,booted=false;
  const safeColor=(v,f)=>/^#[0-9a-f]{6}$/i.test(String(v||''))?String(v).toUpperCase():f;
  const clamp=(n,a,b,f)=>{n=Number(n);return Number.isFinite(n)?Math.max(a,Math.min(b,n)):f};
  function luminance(hex){const c=hex.slice(1).match(/../g).map(x=>parseInt(x,16)/255).map(x=>x<=.03928?x/12.92:Math.pow((x+.055)/1.055,2.4));return .2126*c[0]+.7152*c[1]+.0722*c[2]}
  function contrastText(bg){return luminance(safeColor(bg,'#FFFFFF'))<.34?'#FFFFFF':'#152019'}
  function tint(hex,amount=.93){const h=safeColor(hex,'#173F27'),rgb=h.slice(1).match(/../g).map(x=>parseInt(x,16));const n=rgb.map(v=>Math.round(v+(255-v)*amount));return '#'+n.map(v=>v.toString(16).padStart(2,'0')).join('').toUpperCase()}
  function inject(){
    if(document.getElementById('loom-toast-style'))return;
    const s=document.createElement('style');s.id='loom-toast-style';s.textContent=`
      .loom-toast-stack{position:fixed;z-index:2147483100;display:grid;gap:9px;width:min(410px,calc(100vw - 24px));pointer-events:none;font-family:Inter,ui-sans-serif,system-ui}.loom-toast-stack.bottom-right{right:14px;bottom:max(14px,env(safe-area-inset-bottom))}.loom-toast-stack.bottom-center{left:50%;bottom:max(14px,env(safe-area-inset-bottom));transform:translateX(-50%)}.loom-toast-stack.top-right{right:14px;top:max(14px,env(safe-area-inset-top))}.loom-toast-stack.top-center{left:50%;top:max(14px,env(safe-area-inset-top));transform:translateX(-50%)}
      .loom-toast{--loom-toast-accent:#173F27;--loom-toast-surface:#fff;--loom-toast-text:#173722;position:relative;display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:10px;align-items:start;padding:12px 12px 12px 13px;border:1px solid color-mix(in srgb,var(--loom-toast-accent) 22%,#d9e5dc);border-radius:15px;background:color-mix(in srgb,var(--loom-toast-surface) 96%,transparent);color:var(--loom-toast-text);box-shadow:0 18px 52px rgba(16,48,25,.18);backdrop-filter:blur(16px) saturate(125%);-webkit-backdrop-filter:blur(16px) saturate(125%);pointer-events:auto;overflow:hidden;animation:loomToastIn .2s ease both}
      .loom-toast[data-type=error]{--loom-semantic:#B83030}.loom-toast[data-type=warning]{--loom-semantic:#A06D0A}.loom-toast[data-type=success],.loom-toast[data-type=saved]{--loom-semantic:#178044}.loom-toast[data-type=info]{--loom-semantic:var(--loom-toast-accent)}.loom-toast-icon{width:26px;height:26px;border-radius:9px;display:grid;place-items:center;background:color-mix(in srgb,var(--loom-semantic,var(--loom-toast-accent)) 13%,transparent);color:var(--loom-semantic,var(--loom-toast-accent));font:950 13px/1 system-ui}.loom-toast-copy{min-width:0}.loom-toast-title{display:block;font-size:11px;line-height:1.25;font-weight:950}.loom-toast-message{display:block;margin-top:3px;font-size:10px;line-height:1.5;color:color-mix(in srgb,var(--loom-toast-text) 72%,transparent);overflow-wrap:anywhere}.loom-toast-close{border:0;background:transparent;color:inherit;opacity:.55;cursor:pointer;width:26px;height:26px;border-radius:8px;font-size:17px;line-height:1}.loom-toast-close:hover,.loom-toast-close:focus-visible{opacity:1;background:color-mix(in srgb,var(--loom-toast-accent) 8%,transparent);outline:none}.loom-toast-progress{position:absolute;left:0;bottom:0;height:3px;background:var(--loom-semantic,var(--loom-toast-accent));transform-origin:left center;animation:loomToastProgress linear both}.loom-toast.is-leaving{animation:loomToastOut .16s ease both}
      .loom-tooltip{position:fixed;z-index:2147483200;max-width:min(310px,calc(100vw - 24px));padding:8px 10px;border-radius:10px;background:#132018;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.2);font:800 9px/1.45 Inter,system-ui;pointer-events:none;opacity:0;transform:translateY(3px);transition:.12s ease}.loom-tooltip.open{opacity:1;transform:none}.loom-inline-tip{display:inline-grid;place-items:center;width:17px;height:17px;margin-left:5px;border:1px solid #c8d9cc;border-radius:50%;background:#f5faf6;color:#456250;font:950 9px/1 system-ui;cursor:help;vertical-align:middle}.loom-require-flash{animation:loomRequireFlash .7s ease}.loom-save-state{display:inline-flex;align-items:center;gap:5px;padding:4px 7px;border:1px solid #d7e5da;border-radius:999px;background:#f7fbf8;color:#607268;font:900 8px/1 Inter,system-ui}.loom-save-state[data-state=saving]{color:#7a5a00;background:#fff8df;border-color:#eadba3}.loom-save-state[data-state=saved]{color:#17663a;background:#eaf7ee;border-color:#c9e5d0}
      @keyframes loomToastIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}@keyframes loomToastOut{to{opacity:0;transform:translateY(5px) scale(.985)}}@keyframes loomToastProgress{from{transform:scaleX(1)}to{transform:scaleX(0)}}@keyframes loomRequireFlash{0%,100%{box-shadow:none}35%{box-shadow:0 0 0 4px rgba(190,137,15,.16);border-color:#d3a12f}}
      @media(max-width:600px){.loom-toast-stack{left:8px!important;right:8px!important;bottom:max(8px,env(safe-area-inset-bottom))!important;top:auto!important;transform:none!important;width:auto}.loom-toast{border-radius:14px;padding:11px}.loom-toast-title{font-size:11px}.loom-toast-message{font-size:10px}}
      @media(prefers-reduced-motion:reduce){.loom-toast,.loom-toast.is-leaving,.loom-toast-progress,.loom-require-flash{animation:none!important}}
    `;document.head.appendChild(s);
  }
  function configureGlobal(payload){
    const cfg=payload?.settings?.['loom.toast']||payload?.['loom.toast']||payload||{};
    globalCfg={
      position:['bottom-right','bottom-center','top-right','top-center'].includes(String(cfg.position))?String(cfg.position):DEFAULTS.position,
      durationMs:clamp(cfg.durationMs,1200,12000,DEFAULTS.durationMs),saveDurationMs:clamp(cfg.saveDurationMs,900,8000,DEFAULTS.saveDurationMs),maxVisible:clamp(cfg.maxVisible,1,8,DEFAULTS.maxVisible),
      accentColor:safeColor(cfg.accentColor,DEFAULTS.accentColor),surfaceColor:safeColor(cfg.surfaceColor,DEFAULTS.surfaceColor),textColor:safeColor(cfg.textColor,DEFAULTS.textColor)
    };
    if(container){container.className=`loom-toast-stack ${globalCfg.position}`}
    return {...globalCfg};
  }
  function configureProject(cfg={}){projectTheme={...cfg};return effectiveTheme()}
  function clearProjectTheme(){projectTheme=null}
  function effectiveTheme(){
    const p=projectTheme||{},mode=String(p.mode||'loom');
    if(mode==='project'){
      const accent=safeColor(p.projectPrimary,globalCfg.accentColor),secondary=safeColor(p.projectAccent,accent);
      return{accent,surface:tint(secondary,.955),text:'#152019'};
    }
    if(mode==='custom')return{accent:safeColor(p.customAccent,globalCfg.accentColor),surface:safeColor(p.customSurface,globalCfg.surfaceColor),text:safeColor(p.customText,globalCfg.textColor)};
    return{accent:globalCfg.accentColor,surface:globalCfg.surfaceColor,text:globalCfg.textColor};
  }
  function stack(){inject();if(container?.isConnected)return container;container=document.createElement('div');container.className=`loom-toast-stack ${globalCfg.position}`;container.setAttribute('aria-live','polite');container.setAttribute('aria-relevant','additions');document.body?.appendChild(container);return container}
  function remove(el){if(!el||el.dataset.leaving)return;el.dataset.leaving='1';el.classList.add('is-leaving');setTimeout(()=>el.remove(),180)}
  function show(message,opts={}){
    if(!message)return null;const type=String(opts.type||'info'),theme=effectiveTheme(),root=stack();if(!root)return null;
    const dedupe=String(opts.dedupeKey||'');if(dedupe){const old=root.querySelector(`[data-dedupe="${CSS.escape(dedupe)}"]`);if(old)old.remove()}
    const el=document.createElement('div');el.className='loom-toast';el.dataset.type=type;if(dedupe)el.dataset.dedupe=dedupe;el.setAttribute('role',type==='error'?'alert':'status');el.style.setProperty('--loom-toast-accent',theme.accent);el.style.setProperty('--loom-toast-surface',theme.surface);el.style.setProperty('--loom-toast-text',theme.text);
    const icon={success:'✓',saved:'✓',warning:'!',error:'×',info:'i'}[type]||'i';const title=opts.title||({success:'Applied',saved:'Saved',warning:'Check this',error:'Something needs attention',info:'LOOM'}[type]||'LOOM');
    const i=document.createElement('div');i.className='loom-toast-icon';i.textContent=icon;const copy=document.createElement('div');copy.className='loom-toast-copy';const strong=document.createElement('strong');strong.className='loom-toast-title';strong.textContent=title;const span=document.createElement('span');span.className='loom-toast-message';span.textContent=String(message);copy.append(strong,span);const close=document.createElement('button');close.type='button';close.className='loom-toast-close';close.setAttribute('aria-label','Dismiss notification');close.textContent='×';close.onclick=()=>remove(el);el.append(i,copy,close);
    const duration=opts.sticky?0:clamp(opts.durationMs,700,30000,type==='saved'?globalCfg.saveDurationMs:globalCfg.durationMs);if(duration){const bar=document.createElement('div');bar.className='loom-toast-progress';bar.style.animationDuration=`${duration}ms`;el.appendChild(bar);setTimeout(()=>remove(el),duration)}
    root.appendChild(el);while(root.children.length>globalCfg.maxVisible)root.firstElementChild?.remove();return el;
  }
  const saved=(m='Changes saved.',o={})=>show(m,{...o,type:'saved'}),success=(m,o={})=>show(m,{...o,type:'success'}),warning=(m,o={})=>show(m,{...o,type:'warning'}),error=(m,o={})=>show(m,{...o,type:'error'}),info=(m,o={})=>show(m,{...o,type:'info'}),required=(m='Complete the required acknowledgement before continuing.',o={})=>show(m,{...o,type:'warning',title:o.title||'Required step'});
  function setSaveState(node,state,label){if(!node)return;node.dataset.state=state;node.textContent=label||({saving:'Saving…',saved:'Draft saved',idle:'Auto-save ready'}[state]||state)}
  function hideTip(){tooltip?.classList.remove('open')}
  function showTip(target){const msg=target?.dataset?.loomTip;if(!msg)return;inject();if(!tooltip){tooltip=document.createElement('div');tooltip.className='loom-tooltip';tooltip.setAttribute('role','tooltip');document.body.appendChild(tooltip)}tooltip.textContent=msg;tooltip.classList.add('open');requestAnimationFrame(()=>{const r=target.getBoundingClientRect(),t=tooltip.getBoundingClientRect();let left=Math.min(innerWidth-t.width-8,Math.max(8,r.left+r.width/2-t.width/2)),top=r.bottom+7;if(top+t.height>innerHeight-8)top=Math.max(8,r.top-t.height-7);tooltip.style.left=`${left}px`;tooltip.style.top=`${top}px`})}
  function bindDelegates(){
    document.addEventListener('mouseover',e=>{const t=e.target.closest?.('[data-loom-tip]');if(t)showTip(t)});document.addEventListener('mouseout',e=>{if(e.target.closest?.('[data-loom-tip]'))hideTip()});document.addEventListener('focusin',e=>{const t=e.target.closest?.('[data-loom-tip]');if(t)showTip(t)});document.addEventListener('focusout',hideTip);window.addEventListener('scroll',hideTip,true);window.addEventListener('resize',hideTip);
    document.addEventListener('click',e=>{const trigger=e.target.closest?.('[data-loom-requires]');if(!trigger)return;let needed=null;try{needed=document.querySelector(trigger.dataset.loomRequires)}catch{}if(needed?.checked)return;e.preventDefault();e.stopImmediatePropagation();const host=needed?.closest('label,.ack,.loom-entry-ack')||needed;if(host){host.classList.remove('loom-require-flash');void host.offsetWidth;host.classList.add('loom-require-flash')}required(trigger.dataset.loomRequireMessage||'Check the acknowledgement box first.');needed?.focus?.() },true);
  }
  async function boot(){if(booted)return;booted=true;inject();bindDelegates();try{const r=await fetch(`${apiBase}/global-settings.php?_=${Date.now()}`,{cache:'no-store'});if(r.ok)configureGlobal(await r.json())}catch{}}
  window.addEventListener('loom:toast',e=>{const d=e.detail||{};show(d.message||d.text||'',d)});
  window.LoomToast=Object.freeze({show,saved,success,warning,error,info,required,configureGlobal,configureProject,clearProjectTheme,effectiveTheme,setSaveState,boot});
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
