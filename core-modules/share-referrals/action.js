// @loom-file release=0.15.26 revision=2 policy=package-priority
export function createModule(ctx){
  let button=null,observer=null,statsBusy=false;
  async function injectReferralStats(){
    if(statsBusy||!window.LoomShare)return;const profile=document.querySelector('[data-module="core.user.profile"]');if(!profile||profile.querySelector('[data-loom-referral-stats]'))return;
    statsBusy=true;try{
      const stats=await window.LoomShare.stats(ctx.identity.clientId);if(!profile.isConnected||profile.querySelector('[data-loom-referral-stats]'))return;
      const section=document.createElement('section');section.className='lup-section';section.dataset.loomReferralStats='1';
      const referred=stats?.referredBy?`<div class="lup-note">🤝 Referred by <b>${String(stats.referredBy.username||'LOOM user').replace(/[<>&]/g,'')}</b>${stats.referredBy.createdAt?` · ${new Date(stats.referredBy.createdAt).toLocaleDateString()}`:''}</div>`:'';
      section.innerHTML=`<div class="lup-eyebrow">Sharing network</div><h3>Referrals</h3><div class="lup-stats" style="grid-template-columns:repeat(4,minmax(0,1fr))"><div class="lup-stat"><strong>${Number(stats?.shareLinks||0)}</strong><span>Share links</span></div><div class="lup-stat"><strong>${Number(stats?.clicks||0)}</strong><span>Link opens</span></div><div class="lup-stat"><strong>${Number(stats?.uniqueVisitors||0)}</strong><span>Visitors</span></div><div class="lup-stat"><strong>${Number(stats?.referrals||0)}</strong><span>Referrals</span></div></div>${referred}`;
      const anchor=profile.querySelector('.lup-section');if(anchor)anchor.before(section);else profile.appendChild(section);
    }catch{}finally{statsBusy=false}
  }
  return {
    async mount(){},
    async activate(){
      await window.LoomShare?.init?.({identity:ctx.identity,project:ctx.project,apiBase:ctx.apiBase});
      const slot=document.querySelector('[data-loom-shell-slot="share"]');
      if(slot&&window.LoomShare){button=window.LoomShare.createButton('header');button.className='loom-profile-dock-button loom-share-button';slot.appendChild(button);window.LoomShare.bindButton(button,{identity:ctx.identity,project:ctx.project,apiBase:ctx.apiBase});}
      setTimeout(injectReferralStats,700);
      observer=new MutationObserver(()=>{if(!document.querySelector('[data-loom-referral-stats]'))setTimeout(injectReferralStats,250)});observer.observe(document.body,{childList:true,subtree:true});
      await ctx.log?.('sharing.ready',{project:ctx.project,referralAware:true,profileStats:true});
      return()=>{observer?.disconnect();button?.remove()};
    },
    async deactivate(){observer?.disconnect();observer=null;button?.remove();button=null},
    async unmount(){observer?.disconnect();observer=null;button?.remove();button=null}
  };
}
export default createModule;
