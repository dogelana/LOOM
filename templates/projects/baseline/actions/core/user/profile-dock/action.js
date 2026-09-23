// @loom-file release=0.15.02 revision=4 policy=package-priority
export async function createModule(ctx){
  let button=null,overlay=null,bank=null,observer=null,captured=null,restore=null;

  function targetFrame(){
    return document.querySelector('[data-loom-frame-for="core.user.profile"]')
      || document.querySelector('[data-loom-module-content="core.user.profile"]')?.closest('.loom-module-frame')
      || null;
  }

  function capture(){
    const frame=targetFrame();
    if(!frame||!bank||bank.contains(frame))return;
    restore={parent:frame.parentNode,next:frame.nextSibling};
    captured=frame;
    frame.classList.add('loom-profile-dock-captured');
    frame.hidden=true;
    bank.appendChild(frame);
  }

  function open(){
    capture();
    if(!overlay)return;
    if(captured)captured.hidden=false;
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
  }

  function close(){
    if(!overlay)return;
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden','true');
    if(captured)captured.hidden=true;
    document.body.style.overflow='';
  }

  function mountButton(){
    const slot=document.querySelector('[data-loom-shell-slot="profile"]')||document.getElementById('loomProfileDockSlot');
    if(!slot)return false;
    if(slot.querySelector('[data-profile-dock-button]'))return true;
    button=document.createElement('button');
    button.type='button';
    button.dataset.profileDockButton='1';
    button.className='loom-profile-dock-button';
    button.innerHTML=`<span class="loom-profile-dock-icon" aria-hidden="true">👤</span><span>${ctx.config.label||'User Profile'}</span>`;
    button.addEventListener('click',open);
    button.addEventListener('contextmenu',e=>{e.preventDefault();window.LoomIdentityEntry?.show?.()});
    slot.appendChild(button);
    return true;
  }

  function mountOverlay(){
    overlay=document.createElement('div');
    overlay.className='loom-profile-dock-overlay';
    overlay.setAttribute('aria-hidden','true');
    overlay.innerHTML=`
      <section class="loom-profile-dock-dialog" role="dialog" aria-modal="true" aria-label="User Profile">
        <header class="loom-profile-dock-dialog-head">
          <div><span aria-hidden="true">👤</span> <strong>${ctx.config.label||'User Profile'}</strong></div>
          <div style="display:flex;gap:6px;align-items:center"><button type="button" data-profile-switch style="border:0;border-radius:9px;padding:7px 9px;font-weight:900;cursor:pointer">🔄 Switch user</button><button type="button" data-profile-dock-close aria-label="Close User Profile">×</button></div>
        </header>
        <div class="loom-profile-dock-bank" data-loom-region="profile-dock-capture"></div>
      </section>`;
    document.body.appendChild(overlay);
    bank=overlay.querySelector('.loom-profile-dock-bank');
    overlay.querySelector('[data-profile-dock-close]').onclick=close;overlay.querySelector('[data-profile-switch]').onclick=()=>{close();window.LoomIdentityEntry?.show?.()};
    overlay.addEventListener('click',e=>{if(e.target===overlay)close()});
    addEventListener('keydown',onKey);
  }

  function onKey(e){if(e.key==='Escape'&&overlay?.classList.contains('open'))close()}

  function restoreProfile(){
    if(!captured)return;
    captured.classList.remove('loom-profile-dock-captured');
    captured.hidden=false;
    if(restore?.parent?.isConnected)restore.parent.insertBefore(captured,restore.next?.isConnected?restore.next:null);
    else ctx.mountRoot?.appendChild(captured);
    captured=null;restore=null;
  }

  return{
    async mount(){},
    async activate(){
      mountOverlay();
      mountButton();
      observer=new MutationObserver(()=>{mountButton();capture()});
      observer.observe(document.body,{childList:true,subtree:true});
      capture();
      await ctx.log('profile-dock.ready',{target:'core.user.profile',shellSlot:'profile'});
      return()=>{};
    },
    async deactivate(){
      observer?.disconnect();removeEventListener('keydown',onKey);close();restoreProfile();button?.remove();overlay?.remove();
      button=overlay=bank=null;
    },
    async unmount(){
      observer?.disconnect();removeEventListener('keydown',onKey);close();restoreProfile();button?.remove();overlay?.remove();
    }
  };
}
