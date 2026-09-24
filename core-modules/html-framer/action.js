// LOOM HTML Framer manager module.
// Runtime frame modules are generated dynamically from Instance Vault state.
// This manager intentionally mounts no project-page UI.
export function createModule(ctx){
  return {
    async mount(){},
    async activate(){await ctx.log('html-framer.manager.active',{project:ctx.project})},
    async deactivate(){},
    async unmount(){}
  };
}
