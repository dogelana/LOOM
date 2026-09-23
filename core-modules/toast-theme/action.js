// @loom-file release=0.15.15 revision=1 policy=package-priority
export function createModule(ctx){
  let configured=false;
  function apply(){
    if(!window.LoomToast)return false;
    window.LoomToast.configureProject({
      mode:String(ctx.config.colorMode||'project'),
      projectPrimary:String(ctx.config.projectPrimary||'#111111'),projectAccent:String(ctx.config.projectAccent||'#168346'),
      customAccent:String(ctx.config.customAccent||'#168346'),customSurface:String(ctx.config.customSurface||'#FFFFFF'),customText:String(ctx.config.customText||'#173722')
    });configured=true;return true;
  }
  return{
    async mount(){},
    async activate(){const ok=apply();await ctx.log('toast-theme.ready',{mode:ctx.config.colorMode||'project',available:ok});return()=>{if(configured)window.LoomToast?.clearProjectTheme?.()}},
    async deactivate(){if(configured)window.LoomToast?.clearProjectTheme?.();configured=false},
    async unmount(){if(configured)window.LoomToast?.clearProjectTheme?.();configured=false}
  };
}
