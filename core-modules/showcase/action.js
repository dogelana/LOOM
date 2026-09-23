// @loom-file release=0.15.16 revision=2 policy=package-priority
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function cleanBio(v){return String(v??'').trim().slice(0,1800)}
function hex(v,f){const s=String(v||'').trim();return /^#[0-9a-f]{6}$/i.test(s)?s.toUpperCase():f}
function mix(a,b,t){a=hex(a,'#168346');b=hex(b,'#FFFFFF');t=Math.max(0,Math.min(1,Number(t)||0));const av=parseInt(a.slice(1),16),bv=parseInt(b.slice(1),16),out=[16,8,0].map(sh=>Math.round(((av>>sh)&255)+((((bv>>sh)&255)-((av>>sh)&255))*t)));return '#'+out.map(n=>n.toString(16).padStart(2,'0')).join('').toUpperCase()}
export function createModule(ctx){
  let root=null,project=null;
  async function loadProject(){
    try{
      const q=new URLSearchParams({_ : Date.now().toString()});
      if(ctx.identity?.clientId)q.set('clientId',ctx.identity.clientId);
      const r=await ctx.fetchApi(`projects.php?${q.toString()}`,{cache:'no-store'}),j=await r.json();
      if(r.ok)project=(j.projects||[]).find(p=>p.slug===ctx.project)||null;
    }catch{project=null}
  }
  function imageUrl(){
    if(!ctx.config?.hasImage)return '';
    const asset=String(ctx.config?.imageAsset||'assets/showcase.png').replace(/^\/+/, '');
    const q=new URLSearchParams({project:ctx.project,path:asset,v:String(ctx.config?.imageVersion||Date.now())});
    return ctx.apiUrl(`project-asset.php?${q.toString()}`);
  }
  function effectiveBio(){return ctx.config?.bioMode==='custom'?cleanBio(ctx.config?.bioOverride):cleanBio(project?.bio)}
  function mediaBackground(){
    const mode=String(ctx.config?.imageBackgroundMode||'transparent');
    const primary=hex(ctx.config?.projectPrimary,'#111111'),accent=hex(ctx.config?.projectAccent,'#168346');
    if(mode==='project-accent-soft')return mix(accent,'#FFFFFF',.88);
    if(mode==='project-primary-soft')return mix(primary,'#FFFFFF',.90);
    if(mode==='page')return 'var(--loom-page-background-base,#F6FAF5)';
    if(mode==='loom-soft')return '#EEF6F0';
    if(mode==='white')return '#FFFFFF';
    if(mode==='custom')return hex(ctx.config?.imageBackgroundColor,'#FFFFFF');
    return 'transparent';
  }
  function applyPresentation(){
    if(!root)return;
    root.style.setProperty('--loom-showcase-media-bg',mediaBackground());
    root.style.setProperty('--loom-showcase-image-fit',ctx.config?.imageFit==='cover'?'cover':'contain');
    root.style.setProperty('--loom-showcase-image-padding',`${Math.max(0,Math.min(80,Number(ctx.config?.imagePadding||0)))}px`);
  }
  function render(){
    if(!root)return;
    const bio=effectiveBio(),img=imageUrl(),name=project?.name||ctx.project||'Project';
    root.classList.toggle('loom-showcase-no-image',!img);
    root.innerHTML=`<section class="loom-showcase-card">${img?`<figure class="loom-showcase-media"><img src="${esc(img)}" alt="${esc(name)} showcase image"></figure>`:''}<div class="loom-showcase-copy"><div class="loom-showcase-eyebrow">SHOWCASE</div><h2>${esc(name)}</h2><p>${bio?esc(bio):'This project has not added a showcase summary yet.'}</p></div></section>`;
    applyPresentation();
  }
  return {
    async mount(){root=document.createElement('div');root.className='loom-showcase';root.dataset.loomShowcase='1';ctx.mount(root);render()},
    async activate(){await loadProject();render();await ctx.log?.('showcase.ready',{bioMode:ctx.config?.bioMode==='custom'?'custom':'project',hasImage:!!ctx.config?.hasImage,imageFit:ctx.config?.imageFit||'contain',imageBackgroundMode:ctx.config?.imageBackgroundMode||'transparent'})},
    async deactivate(){},
    async unmount(){root?.remove();root=null}
  }
}
export default createModule;
