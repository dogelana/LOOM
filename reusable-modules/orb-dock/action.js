// @loom-file release=0.15.16 revision=3 policy=package-priority
export async function createModule(ctx){
  let root=null,overlay=null,bank=null,observer=null;const moved=new Map();
  const descriptors=()=>ctx.listModuleDescriptors();
  const metaFor=d=>d?.presentation?.orb||{};
  const fallbackBadge=name=>{const words=String(name||'').trim().split(/\s+/).filter(Boolean);if(words.length>=2)return (words[0][0]+words[1][0]).toUpperCase();const one=words[0]||'MD';return one.slice(0,2).toUpperCase()};
  function eligible(){return descriptors().filter(d=>d?.presentation?.role==='content'&&d.action?.id!==ctx.action.id)}
  function settingsFor(d){const m=metaFor(d),saved=ctx.config.orbTargets?.[d.action.id]||{};return {captured:m.required?true:(saved.captured??m.defaultCaptured??false),emoji:saved.emoji??m.defaultEmoji??'',label:m.label||d.action.name||d.action.id,required:!!m.required}}
  function targetNode(id){return [...document.querySelectorAll('[data-module]')].find(n=>n.dataset.module===id&&n!==root)||null}
  function capture(id){const d=descriptors().find(x=>x.action?.id===id);if(!d||!settingsFor(d).captured)return;const n=targetNode(id);if(!n||bank.contains(n))return;if(!moved.has(id))moved.set(id,{parent:n.parentNode,next:n.nextSibling});n.classList.add('loom-orb-captured');n.hidden=true;bank.appendChild(n)}
  function captureAll(){for(const d of eligible())if(settingsFor(d).captured)capture(d.action.id)}
  function close(){overlay?.classList.remove('open');document.body.style.removeProperty('overflow');for(const n of bank?.children||[])n.hidden=true}
  function open(id){capture(id);const n=targetNode(id);if(!n)return;for(const x of bank.children)x.hidden=x!==n;n.hidden=false;overlay.classList.add('open');document.body.style.overflow='hidden';overlay.querySelector('[data-orb-title]').textContent=(descriptors().find(d=>d.action?.id===id)?.action?.name)||id}
  function toolsRow(){return document.querySelector('.loom-footer-orb-slot')?.closest('.loom-footer-tools-row')||document.querySelector('[data-loom-tools-row]')||null}
  function render(){
    const captured=eligible().filter(d=>settingsFor(d).captured);
    const row=toolsRow();
    if(!captured.length){if(row)row.hidden=true;return false}
    if(row)row.hidden=false;
    root=document.createElement('section');root.className='loom-orb-dock';root.dataset.layout=ctx.config.layoutMode==='wrap'?'wrap':'carousel';root.dataset.align=['left','center','right'].includes(ctx.config.alignment)?ctx.config.alignment:'center';root.style.setProperty('--orb-size',`${Math.max(28,Number(ctx.config.orbSize||50))}px`);root.style.setProperty('--orb-name-size',`${Math.max(8,Number(ctx.config.nameSize||11))}px`);root.style.setProperty('--orb-gap',`${Math.max(2,Number(ctx.config.gap||12))}px`);root.style.setProperty('--orb-shadow-blur',`${Math.max(0,Number(ctx.config.shadowBlur||10))}px`);root.style.setProperty('--orb-shadow-y',`${Math.max(0,Number(ctx.config.shadowY||4))}px`);root.style.setProperty('--orb-shadow-opacity',String(Math.max(0,Math.min(60,Number(ctx.config.shadowOpacity||18)))/100));const shadowHex=String(ctx.config.shadowColor||'#1A2D20').replace('#','');const shadowRgb=/^[0-9a-fA-F]{6}$/.test(shadowHex)?[parseInt(shadowHex.slice(0,2),16),parseInt(shadowHex.slice(2,4),16),parseInt(shadowHex.slice(4,6),16)]:[26,45,32];root.style.setProperty('--orb-shadow-rgba',`rgba(${shadowRgb[0]},${shadowRgb[1]},${shadowRgb[2]},${Math.max(0,Math.min(60,Number(ctx.config.shadowOpacity||18)))/100})`);
    if(ctx.config.showSectionTitle!==false){const h=document.createElement('div');h.className='loom-orb-dock-title';h.textContent=ctx.config.sectionTitle||'More Tools';root.appendChild(h)}
    const rail=document.createElement('div');rail.className='loom-orb-rail';
    for(const d of captured){
      const st=settingsFor(d);const item=document.createElement('button');item.type='button';item.className='loom-orb-item';item.dataset.target=d.action.id;item.title=st.label;
      const icon=document.createElement('span');icon.className='loom-orb-icon';if(st.emoji){icon.textContent=st.emoji;icon.classList.add('is-emoji')}else{icon.textContent=fallbackBadge(st.label);icon.classList.add('is-badge')};item.appendChild(icon);
      if(ctx.config.showNames!==false){const name=document.createElement('span');name.className='loom-orb-name';name.textContent=st.label;item.appendChild(name)}
      item.addEventListener('click',()=>open(d.action.id));rail.appendChild(item)
    }
    root.appendChild(rail);ctx.mount(root);
    overlay=document.createElement('div');overlay.className='loom-orb-overlay';overlay.innerHTML=`<div class="loom-orb-dialog"><div class="loom-orb-dialog-head"><strong data-orb-title>Module</strong><button type="button" data-orb-close aria-label="Close">×</button></div><div class="loom-orb-capture-bank" data-loom-region="orb-capture-bank"></div></div>`;document.body.appendChild(overlay);bank=overlay.querySelector('.loom-orb-capture-bank');overlay.querySelector('[data-orb-close]').onclick=close;overlay.addEventListener('click',e=>{if(e.target===overlay)close()});
    observer=new MutationObserver(()=>captureAll());observer.observe(document.body,{childList:true,subtree:true});captureAll();
  }
  return{async mount(){},async activate(){const shown=render();await ctx.log('orb-dock.ready',{captured:eligible().filter(d=>settingsFor(d).captured).map(d=>d.action.id),visible:!!shown});return()=>{}},async deactivate(){observer?.disconnect();for(const [id,loc] of moved){const n=targetNode(id);if(!n)continue;n.classList.remove('loom-orb-captured');n.hidden=false;if(loc.parent?.isConnected)loc.parent.insertBefore(n,loc.next?.isConnected?loc.next:null);else ctx.mountRoot.appendChild(n)}overlay?.remove();root?.remove();const row=toolsRow();if(row)row.hidden=true},async unmount(){observer?.disconnect();overlay?.remove();root?.remove();const row=toolsRow();if(row)row.hidden=true}};
}