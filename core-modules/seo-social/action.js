// @loom-file release=0.15.18 revision=1 policy=package-priority
// SEO/social metadata is rendered server-side so link unfurlers and crawlers see it
// before JavaScript runs. This lightweight module exists so the capability remains
// a normal project-scoped LOOM module with settings, registry visibility, and logs.
export function createModule(ctx){
  return {
    async mount(){},
    async activate(){await ctx.log?.('seo-social.ready',{serverRendered:true,titleMode:ctx.config?.titleMode||'project',descriptionMode:ctx.config?.descriptionMode||'project',imageMode:ctx.config?.imageMode||'auto',robotsMode:ctx.config?.robotsMode||'index-follow'})},
    async deactivate(){},
    async unmount(){}
  };
}
export default createModule;
