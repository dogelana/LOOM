// @loom-file release=0.15.01 revision=1 policy=package-priority
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
function cleanBio(v){return String(v??'').trim().slice(0,1800)}
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
  function effectiveBio(){
    return ctx.config?.bioMode==='custom' ? cleanBio(ctx.config?.bioOverride) : cleanBio(project?.bio);
  }
  function render(){
    if(!root)return;
    const bio=effectiveBio(),img=imageUrl(),name=project?.name||ctx.project||'Project';
    root.classList.toggle('loom-showcase-no-image',!img);
    root.innerHTML=`<section class="loom-showcase-card">${img?`<figure class="loom-showcase-media"><img src="${esc(img)}" alt="${esc(name)} showcase image"></figure>`:''}<div class="loom-showcase-copy"><div class="loom-showcase-eyebrow">SHOWCASE</div><h2>${esc(name)}</h2><p>${bio?esc(bio):'This project has not added a showcase summary yet.'}</p></div></section>`;
  }
  return {
    async mount(){
      root=document.createElement('div');root.className='loom-showcase';root.dataset.loomShowcase='1';ctx.mount(root);render();
    },
    async activate(){await loadProject();render();await ctx.log?.('showcase.ready',{bioMode:ctx.config?.bioMode==='custom'?'custom':'project',hasImage:!!ctx.config?.hasImage})},
    async deactivate(){},
    async unmount(){root?.remove();root=null}
  }
}
export default createModule;
