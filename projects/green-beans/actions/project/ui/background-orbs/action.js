// @loom-file release=0.12.08 revision=3 policy=package-priority
export async function createModule(ctx){
  const extension={
    label:'Green Beans',
    glowColor:'#5DEB6A',
    specialGlowColor:'#FF9A3D',
    orbAsset:'assets/green-beans-emoji-pod.svg',
    specialOrbEmoji:'🥕',
    async getOrbAssetUrl(){
      const moduleRelative=await ctx.resolveAssetPath('assets/green-beans-emoji-pod.svg','module');
      if(moduleRelative)return moduleRelative;
      return ctx.resolveAssetPath(
        'actions/project/ui/background-orbs/assets/green-beans-emoji-pod.svg',
        'project'
      );
    },
    async getSpecialOrbEmoji(){return '🥕'}
  };

  return {
    extensions:{'core.ui.background-orbs.provider':extension},
    async mount(){},
    async activate(){
      ctx.step('register-provider','active');
      const url=await extension.getOrbAssetUrl();
      ctx.step('register-provider','completed',{
        extension:'core.ui.background-orbs.provider',
        label:extension.label,
        glowColor:extension.glowColor,
        specialGlowColor:extension.specialGlowColor,
        orbAsset:extension.orbAsset,
        specialOrbEmoji:extension.specialOrbEmoji,
        resolved:url
      });
      await ctx.log('green-beans.background-orbs-provider.ready',{
        glowColor:extension.glowColor,
        specialGlowColor:extension.specialGlowColor,
        orbAsset:extension.orbAsset,
        specialOrbEmoji:extension.specialOrbEmoji,
        strategy:'bundled-pea-pod-plus-special-carrot'
      });
    },
    async deactivate(){},
    async unmount(){}
  };
}
