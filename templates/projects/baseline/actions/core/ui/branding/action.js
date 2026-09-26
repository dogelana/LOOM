// @loom-file release=0.12.08 revision=2 policy=package-priority
export async function createModule(ctx) {
  let titleBefore = document.title;
  let iconBefore = null;
  let touchBefore = null;
  let projectName = ctx.project;

  const clean = value => String(value ?? '').trim();
  const mimeFor = url => {
    const s=String(url||'').toLowerCase();
    if(s.includes('.ico'))return 'image/x-icon';
    if(s.includes('.svg'))return 'image/svg+xml';
    if(s.includes('.webp'))return 'image/webp';
    if(s.includes('.jpg')||s.includes('.jpeg'))return 'image/jpeg';
    return 'image/png';
  };

  async function projectInfo() {
    try {
      const res=await ctx.fetchApi(`projects.php?_=${Date.now()}`);
      if(!res.ok)return null;
      const data=await res.json();
      return (data.projects||[]).find(p=>p.slug===ctx.project)||null;
    } catch { return null; }
  }

  async function logoAssetPath() {
    try {
      const res=await ctx.fetchApi(`modules.php?project=${encodeURIComponent(ctx.project)}&_=${Date.now()}`);
      if(!res.ok)return 'assets/logo.png';
      const data=await res.json();
      const logo=(data.modules||[]).find(m=>m.action?.id===(ctx.config.logoActionId||'core.ui.load-logo'));
      return logo?.config?.assetPath||'assets/logo.png';
    } catch { return 'assets/logo.png'; }
  }

  function ensureLink(id, rel) {
    let node=document.getElementById(id);
    if(!node){node=document.createElement('link');node.id=id;node.rel=rel;document.head.appendChild(node)}
    return node;
  }

  async function applyBranding() {
    ctx.step('read-project','active');
    const info=await projectInfo();
    projectName=clean(info?.name)||ctx.project;
    ctx.step('read-project','completed',{projectName});

    ctx.step('set-title','active');
    const tagline=clean(ctx.config.tagline)||clean(info?.tagline);
    document.title=tagline ? `${projectName}${ctx.config.titleSeparator||' · '}${tagline}` : projectName;
    document.querySelectorAll('[data-loom-project-name]').forEach(node=>{node.textContent=projectName});
    document.querySelectorAll('[data-loom-project-tagline]').forEach(node=>{node.textContent=tagline;node.hidden=!tagline});
    ctx.step('set-title','completed',{projectName,tagline,title:document.title,visibleChrome:true});

    ctx.step('resolve-favicon','active');
    let faviconPath='';
    if(String(ctx.config.faviconMode||'logo')==='custom') faviconPath=ctx.config.customFaviconPath||'assets/favicon.ico';
    else faviconPath=await logoAssetPath();
    const faviconUrl=await ctx.resolveAssetPath(faviconPath,'project');
    ctx.step('resolve-favicon',faviconUrl?'completed':'failed',{faviconMode:ctx.config.faviconMode||'logo',faviconPath,faviconUrl});

    if(faviconUrl){
      ctx.step('set-favicon','active');
      const icon=ensureLink('loom-project-favicon','icon');
      const touch=ensureLink('loom-project-touch-icon','apple-touch-icon');
      icon.type=mimeFor(faviconUrl); icon.href=faviconUrl;
      touch.href=faviconUrl;
      ctx.step('set-favicon','completed',{faviconUrl,faviconMode:ctx.config.faviconMode||'logo'});
    }
    await ctx.log('branding.applied',{projectName,tagline,documentTitle:document.title,faviconMode:ctx.config.faviconMode||'logo',faviconPath,faviconUrl});
  }

  async function cleanup(reason='deactivate') {
    document.title=titleBefore;
    const icon=document.getElementById('loom-project-favicon'); if(icon) icon.remove();
    const touch=document.getElementById('loom-project-touch-icon'); if(touch) touch.remove();
    await ctx.log('branding.unmounted',{reason});
  }

  return {
    async mount(){},
    async activate(){
      iconBefore=document.querySelector('link[rel="icon"]')?.href||null;
      touchBefore=document.querySelector('link[rel="apple-touch-icon"]')?.href||null;
      await applyBranding();
      return ()=>cleanup('cleanup');
    },
    async deactivate(detail={}){await cleanup(detail.reason||'deactivate')},
    async unmount(detail={}){await cleanup(detail.reason||'unmount')}
  };
}
