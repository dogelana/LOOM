// @loom-file release=0.15.53 revision=7 policy=package-priority
(() => {
  'use strict';

  const injected = new Set();

  function esc(v){
    return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function fmt(v){
    if(!v)return '—';
    try{return new Date(v).toLocaleString()}catch{return String(v)}
  }
  function shortId(v){
    const s=String(v||'');
    return s.length>25?s.slice(0,15)+'…'+s.slice(-8):s||'—';
  }
  function injectStyle(){
    if(injected.has('style'))return;
    injected.add('style');
    const s=document.createElement('style');
    s.dataset.loomGlobalProfile='1';
    s.textContent=`
      .loom-global-profile-button{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border:1px solid #d8e5da;border-radius:9px;background:#fff;color:#345c40;font:900 10px/1 Inter,system-ui;cursor:pointer}
      .loom-global-profile-button:hover{background:#f3faf5}.loom-global-profile-button .pic{font-size:15px;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
      .loom-global-profile-overlay{position:fixed;inset:0;z-index:20000;display:none;place-items:center;padding:18px;background:rgba(8,27,14,.57);backdrop-filter:blur(10px)}
      .loom-global-profile-overlay.open{display:grid}
      .loom-global-profile-dialog{width:min(880px,100%);max-height:92vh;overflow:hidden;display:flex;flex-direction:column;border:1px solid #d9e6dc;border-radius:28px;background:#f7faf7;box-shadow:0 32px 110px rgba(9,38,17,.28)}
      .loom-global-profile-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #dce8de;background:#fff}
      .loom-global-profile-head strong{font:950 14px/1 Inter,system-ui;color:#193923}.loom-global-profile-head small{display:block;margin-top:4px;font:800 9px/1.2 Inter,system-ui;color:#77857c;letter-spacing:.05em;text-transform:uppercase}
      .loom-global-profile-close{width:34px;height:34px;border:0;border-radius:10px;background:#edf5ef;color:#506358;font:800 20px/1 system-ui;cursor:pointer}
      .loom-global-profile-body{overflow:auto;padding:18px;display:grid;gap:14px}
      .lgp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
      .lgp-card{padding:16px;border:1px solid #dce8df;border-radius:18px;background:#fff;min-width:0}
      .lgp-card h3{margin:0 0 4px;font:950 16px/1.15 Inter,system-ui;color:#173b24}.lgp-card p{margin:0 0 12px;color:#728077;font:500 11px/1.5 Inter,system-ui}
      .lgp-avatar-row{display:grid;grid-template-columns:92px 1fr;gap:14px;align-items:center}.lgp-avatar{width:92px;height:92px;border-radius:50%;object-fit:cover;border:1px solid #dbe7dd;background:#f2f6f2}
      .lgp-form{display:grid;gap:8px}.lgp-form input,.lgp-form select{width:100%;padding:10px 11px;border:1px solid #d5e3d8;border-radius:10px;background:#fff;color:#243e2c;font:700 11px/1.2 Inter,system-ui}
      .lgp-actions{display:flex;gap:7px;flex-wrap:wrap}.lgp-btn{border:0;border-radius:10px;padding:9px 11px;background:#177a40;color:#fff;font:900 10px/1 Inter,system-ui;cursor:pointer}.lgp-btn.secondary{background:#edf5ef;color:#3f5d48}.lgp-btn:disabled{opacity:.55;cursor:not-allowed}
      .lgp-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.lgp-kv{padding:10px;border-radius:11px;background:#f7faf7;border:1px solid #e2ebe4;min-width:0}.lgp-kv b{display:block;font:900 8px/1 Inter,system-ui;color:#718078;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px}.lgp-kv code,.lgp-kv strong{font:800 10px/1.35 ui-monospace,SFMono-Regular,Menlo,monospace;color:#294633;word-break:break-all}
      .lgp-message{min-height:15px;color:#457153;font:800 10px/1.4 Inter,system-ui}.lgp-help-link{display:inline-block;margin-top:2px;color:#2b6e43;font:800 10px/1.3 Inter,system-ui;text-decoration:none}.lgp-help-link:hover{text-decoration:underline}.lgp-note{padding:10px 12px;border-radius:12px;background:#eef7f0;border:1px solid #d8e8dc;color:#5c7062;font:600 10px/1.45 Inter,system-ui}
      .lgp-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#e9f6ec;color:#176839;font:900 9px/1 Inter,system-ui}
      .lgp-file{display:none}
      @media(max-width:720px){.loom-global-profile-overlay{padding:7px}.loom-global-profile-dialog{border-radius:20px;max-height:96vh}.loom-global-profile-body{padding:10px}.lgp-grid{grid-template-columns:1fr}.lgp-avatar-row{grid-template-columns:72px 1fr}.lgp-avatar{width:72px;height:72px}.lgp-meta{grid-template-columns:1fr}}
    `;
    document.head.appendChild(s);
  }

  async function json(url,opts={}){
    const r=await fetch(url,{cache:'no-store',...opts});
    const d=await r.json().catch(()=>({}));
    if(!r.ok||d?.ok===false)throw new Error(d?.error||`Request failed (${r.status})`);
    return d;
  }

  async function compressAvatar(file){
    const img=await new Promise((resolve,reject)=>{
      const i=new Image();i.onload=()=>resolve(i);i.onerror=()=>reject(new Error('Could not read that image.'));
      i.src=URL.createObjectURL(file);
    });
    const S=384,canvas=document.createElement('canvas');canvas.width=canvas.height=S;
    const g=canvas.getContext('2d');
    const sw=img.naturalWidth,sh=img.naturalHeight,side=Math.min(sw,sh),sx=(sw-side)/2,sy=(sh-side)/2;
    g.clearRect(0,0,S,S);g.imageSmoothingEnabled=true;g.imageSmoothingQuality='high';
    g.drawImage(img,sx,sy,side,side,0,0,S,S);
    URL.revokeObjectURL(img.src);
    let blob=await new Promise(r=>canvas.toBlob(r,'image/webp',.84));
    if(!blob)blob=await new Promise(r=>canvas.toBlob(r,'image/png'));
    if(!blob)throw new Error('Could not compress that image.');
    return blob;
  }

  class GlobalProfile {
    constructor({apiBase='api',identity,projectsProvider=null}={}){
      injectStyle();
      this.apiBase=String(apiBase).replace(/\/$/,'');
      this.identity=identity||window.LoomIdentity?.get?.('loom-home');
      this.projectsProvider=projectsProvider;
      this.data=null;
      this.overlay=null;
      this.boundButtons=[];
    }
    api(path){return `${this.apiBase}/${path}`}
    defaultAvatar(){return new URL('../assets/loom-default-avatar.svg',this.apiBase.endsWith('/api')?this.apiBase+'/':(document.baseURI||location.href)).href}
    avatarUrl(){
      const a=this.data?.profile?.avatar;
      if(a?.mode==='custom'&&a?.customAvailable){
        return this.api(`profile-avatar.php?action=image&scope=global&clientId=${encodeURIComponent(this.identity.clientId)}&v=${encodeURIComponent(a.customUpdatedAt||Date.now())}`);
      }
      return this._rootAsset('assets/loom-default-avatar.svg');
    }
    _rootAsset(rel){
      try{
        const apiUrl=new URL(this.apiBase+'/',document.baseURI||location.href);
        return new URL('../'+rel,apiUrl).href;
      }catch{return rel}
    }
    async load(){
      const clientId=this.identity.clientId;
      const [gp,account,guestState,projects]=await Promise.all([
        json(this.api(`global-profile.php?clientId=${encodeURIComponent(clientId)}&_=${Date.now()}`)),
        json(this.api('account.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'status',clientId})}),
        json(this.api('guest-identity.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'status',clientId})}),
        this.projectsProvider?this.projectsProvider():json(this.api(`projects.php?clientId=${encodeURIComponent(clientId)}&_=${Date.now()}`)).catch(()=>({projects:[]}))
      ]);
      this.data={profile:{...gp.profile,avatar:gp.avatar},account:account.account||{},privilege:account.privilege||{},guest:guestState.guest||account.guest||{},attachedGuests:guestState.attachedGuests||account.attachedGuestHistories||[],projects:projects.projects||[]};
      return this.data;
    }
    async open(){
      if(!this.overlay)this.mountOverlay();
      this.overlay.classList.add('open');this.overlay.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';
      await this.refresh();
    }
    close(){
      if(!this.overlay)return;
      this.overlay.classList.remove('open');this.overlay.setAttribute('aria-hidden','true');document.body.style.overflow='';
    }
    bindButton(button){
      if(!button)return;
      button.addEventListener('click',()=>this.open().catch(e=>alert(e.message)));
      this.boundButtons.push(button);
    }
    mountOverlay(){
      this.overlay=document.createElement('div');
      this.overlay.className='loom-global-profile-overlay';this.overlay.setAttribute('aria-hidden','true');
      this.overlay.innerHTML=`<section class="loom-global-profile-dialog" role="dialog" aria-modal="true" aria-label="LOOM Profile"><header class="loom-global-profile-head"><div><strong>👤 LOOM Profile</strong><small>Global identity · not project-specific</small></div><button class="loom-global-profile-close" type="button" aria-label="Close">×</button></header><div class="loom-global-profile-body"><div class="lgp-note">Loading LOOM profile…</div></div></section>`;
      document.body.appendChild(this.overlay);
      this.overlay.querySelector('.loom-global-profile-close').onclick=()=>this.close();
      this.overlay.addEventListener('click',e=>{if(e.target===this.overlay)this.close()});
      addEventListener('keydown',e=>{if(e.key==='Escape'&&this.overlay?.classList.contains('open'))this.close()});
    }
    async refresh(message=''){
      try{await this.load();this.render(message)}catch(e){this.renderError(e.message)}
    }
    renderError(message){
      const body=this.overlay?.querySelector('.loom-global-profile-body');if(body)body.innerHTML=`<div class="lgp-note">${esc(message||'Could not load LOOM profile.')}</div>`;
    }
    render(message=''){
      const d=this.data,p=d.profile||{},a=d.account||{},projects=d.projects||[],guest=d.guest||{},attachedGuests=d.attachedGuests||[];
      const avatar=this.avatarUrl();
      const authenticated=!!a.authenticated,registered=!!a.registered;
      const body=this.overlay.querySelector('.loom-global-profile-body');
      body.innerHTML=`
        <div class="lgp-grid">
          <section class="lgp-card">
            <span class="lgp-badge">LOOM-wide identity</span>
            <h3 style="margin-top:8px">${esc(p.displayName||p.username||'LOOM Profile')}</h3>
            <p>This display name and picture exist outside projects. Each project may inherit them or use its own override.</p>
            <div class="lgp-avatar-row">
              <img class="lgp-avatar" src="${esc(avatar)}" alt="LOOM profile picture">
              <div>
                <div class="lgp-actions">
                  <button class="lgp-btn secondary" data-act="avatar-default" type="button">LOOM Default</button>
                  <button class="lgp-btn secondary" data-act="avatar-upload" type="button">Upload Picture</button>
                </div>
                ${projects.length?`<div class="lgp-actions" style="margin-top:8px"><select data-role="project-pull" aria-label="Choose project">${projects.map(x=>`<option value="${esc(x.slug)}">${esc(x.name||x.slug)}</option>`).join('')}</select><button class="lgp-btn secondary" data-act="avatar-pull" type="button">Pull from Project</button></div>`:''}
                <input class="lgp-file" data-role="avatar-file" type="file" accept="image/*">
                <div class="lgp-message" data-role="avatar-message"></div>
              </div>
            </div>
            <form class="lgp-form" data-role="username-form" style="margin-top:13px">
              <input data-role="username" maxlength="40" autocomplete="nickname" value="${esc(p.displayName||p.username||'')}" placeholder="LOOM display name">
              <button class="lgp-btn" type="submit">Save Display Name</button>
              <div class="lgp-message" data-role="username-message"></div>
            </form>
          </section>

          <section class="lgp-card">
            <span class="lgp-badge">${authenticated?'Signed in':registered?'Account linked':'LOOM account'}</span>
            <h3 style="margin-top:8px">${esc(p.displayName||a.globalUsername||p.username||'LOOM user')}</h3>
            ${registered?`
              <div class="lgp-meta">
                <div class="lgp-kv"><b>Email</b><strong>${esc(a.email||'—')}</strong></div>
                <div class="lgp-kv"><b>User ID</b><code title="${esc(a.userId||'')}">${esc(shortId(a.userId))}</code></div>
                <div class="lgp-kv"><b>Privilege</b><strong>${esc(a.effectivePrivilege||a.privilege||'User')}</strong></div>
                <div class="lgp-kv"><b>Authorization</b><code>${esc(a.authorizationSource||'account')}</code></div>
              </div>
              ${authenticated?`<div class="lgp-actions" style="margin-top:12px"><button class="lgp-btn secondary" data-act="logout" type="button">Sign Out</button></div>`:`
                <p style="margin-top:12px">This browser is linked to an account, but you must sign in before changing protected account-owned profile data.</p>
                ${this.loginForm()}
              `}
            `:`
              <p>Make this LOOM identity permanent without entering a project first.</p>
              ${this.registerForm()}
              <div style="height:10px"></div>
              <p>Already have a LOOM account?</p>
              ${this.loginForm()}
            `}
          </section>
        </div>

        <section class="lgp-card">
          <h3>LOOM profile details</h3>
          <div class="lgp-meta">
            <div class="lgp-kv"><b>Global Profile ID</b><code>${esc(p.profileId||'—')}</code></div>
            <div class="lgp-kv"><b>Client ID</b><code title="${esc(this.identity.clientId)}">${esc(shortId(this.identity.clientId))}</code></div>
            <div class="lgp-kv"><b>Profile since</b><strong>${esc(fmt(p.createdAt))}</strong></div>
            <div class="lgp-kv"><b>Last saved</b><strong>${esc(fmt(p.updatedAt))}</strong></div>
          </div>
          <div class="lgp-note" style="margin-top:10px"><b>No project identity is shown here.</b> Open User Profile from inside a project to manage that project's username, avatar override, project identity metadata, and project-specific analytics.</div>
        </section>

        <section class="lgp-card">
          <span class="lgp-badge">${registered?'Attached history':'Durable Guest Identity'}</span>
          <h3 style="margin-top:8px">${registered?'Guest Histories attached to this account':'Protect Guest History'}</h3>
          ${registered?`
            <p>Guest Histories from devices you sign in on remain preserved for provenance and are attached to this permanent account one device at a time.</p>
            <div class="lgp-meta">
              <div class="lgp-kv"><b>Attached histories</b><strong>${attachedGuests.length}</strong></div>
              <div class="lgp-kv"><b>Current Guest ID</b><code>${esc(shortId(guest.guestId||'—'))}</code></div>
            </div>
            ${attachedGuests.length?`<div class="lgp-note" style="margin-top:10px">${attachedGuests.slice(0,8).map(g=>`${esc(shortId(g.guestId))} · ${esc(g.status||'attached')} · ${esc(fmt(g.attachedAt||g.updatedAt))}`).join('<br>')}</div>`:''}
          `:`
            <p>Your work is saved even without an account. A recovery code lets you regain this Guest Identity if the device is lost before you create an account.</p>
            <div class="lgp-meta">
              <div class="lgp-kv"><b>Guest ID</b><code title="${esc(guest.guestId||'')}">${esc(shortId(guest.guestId||'—'))}</code></div>
              <div class="lgp-kv"><b>Recovery</b><strong>${guest.recoveryProtected?`Protected ${esc(guest.recoveryHint||'')}`:'Not protected yet'}</strong></div>
            </div>
            <div class="lgp-actions" style="margin-top:10px"><button class="lgp-btn secondary" data-act="issue-recovery" type="button">${guest.recoveryProtected?'Rotate Recovery Code':'Protect Guest Progress'}</button></div>
            <div class="lgp-message" data-role="recovery-message"></div>
            <div class="lgp-form" style="margin-top:10px"><input data-role="recovery-code" placeholder="LOOM-XXXX-XXXX-XXXX-XXXX"><button class="lgp-btn secondary" data-act="recover-guest" type="button">Recover Guest History</button></div>
          `}
        </section>
        <div class="lgp-message" data-role="main-message">${esc(message)}</div>
      `;
      this.bindControls();
    }
    registerForm(){
      return `<form class="lgp-form" data-role="register-form"><input type="email" data-role="register-email" placeholder="Email address" autocomplete="email"><input type="password" data-role="register-password" placeholder="Password · 8+ characters" autocomplete="new-password"><button class="lgp-btn" type="submit">Make LOOM Account Permanent</button><div class="lgp-message" data-role="register-message"></div></form>`;
    }
    loginForm(){
      return `<form class="lgp-form" data-role="login-form"><input type="email" data-role="login-email" placeholder="Email address" autocomplete="email"><input type="password" data-role="login-password" placeholder="Password" autocomplete="current-password"><button class="lgp-btn secondary" type="submit">Sign In</button><a class="lgp-help-link" href="${esc(this._rootAsset('forgot-password.php'))}">Forgot password?</a><div class="lgp-message" data-role="login-message"></div></form>`;
    }
    bindControls(){
      const q=s=>this.overlay.querySelector(s),clientId=this.identity.clientId;
      q('[data-role="username-form"]')?.addEventListener('submit',async e=>{
        e.preventDefault();const m=q('[data-role="username-message"]');
        try{await json(this.api('global-profile.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'set-username',clientId,username:q('[data-role="username"]')?.value||''})});await this.refresh('LOOM display name saved.')}catch(err){if(m)m.textContent=err.message}
      });
      const file=q('[data-role="avatar-file"]');
      q('[data-act="avatar-upload"]')?.addEventListener('click',()=>file?.click());
      file?.addEventListener('change',async()=>{
        const f=file.files?.[0];if(!f)return;const m=q('[data-role="avatar-message"]');
        try{
          if(m)m.textContent='Compressing image…';
          const b=await compressAvatar(f),fd=new FormData();fd.append('clientId',clientId);fd.append('avatar',b,'avatar.webp');
          const r=await fetch(this.api('global-profile.php'),{method:'POST',body:fd});const d=await r.json().catch(()=>({}));
          if(!r.ok||!d.ok)throw new Error(d.error||'Upload failed');
          await this.refresh('LOOM profile picture saved.');
        }catch(err){if(m)m.textContent=err.message}
      });
      q('[data-act="avatar-default"]')?.addEventListener('click',async()=>{
        try{await json(this.api('global-profile.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'set-avatar-mode',clientId,mode:'loom-default'})});await this.refresh('LOOM default profile picture restored.')}catch(e){q('[data-role="avatar-message"]').textContent=e.message}
      });
      q('[data-act="avatar-pull"]')?.addEventListener('click',async()=>{
        const project=q('[data-role="project-pull"]')?.value,m=q('[data-role="avatar-message"]');
        try{if(m)m.textContent='Retrieving project picture…';await json(this.api('global-profile.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'copy-project-avatar',clientId,project})});await this.refresh('Project picture copied into your LOOM profile.')}catch(e){if(m)m.textContent=e.message}
      });
      q('[data-role="register-form"]')?.addEventListener('submit',async e=>{
        e.preventDefault();const m=q('[data-role="register-message"]');
        try{await json(this.api('account.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'register',clientId,email:q('[data-role="register-email"]')?.value||'',password:q('[data-role="register-password"]')?.value||''})});await this.refresh('Permanent LOOM account created.')}catch(err){if(m)m.textContent=err.message}
      });
      q('[data-role="login-form"]')?.addEventListener('submit',async e=>{
        e.preventDefault();const m=q('[data-role="login-message"]');
        try{await json(this.api('account.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'login',clientId,email:q('[data-role="login-email"]')?.value||'',password:q('[data-role="login-password"]')?.value||''})});await this.refresh('Signed in.')}catch(err){if(m)m.textContent=err.message}
      });
      q('[data-act="issue-recovery"]')?.addEventListener('click',async()=>{
        const m=q('[data-role="recovery-message"]');
        try{const r=await json(this.api('guest-identity.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'issue-recovery',clientId})});if(m)m.innerHTML=`<b>Recovery code:</b> <code>${esc(r.recoveryCode||'')}</code><br>Save it somewhere safe. LOOM stores only a one-way hash.`;try{localStorage.setItem(`loom:guest-recovery:${r.guest?.guestId||'current'}`,r.recoveryCode||'')}catch{}}catch(e){if(m)m.textContent=e.message}
      });
      q('[data-act="recover-guest"]')?.addEventListener('click',async()=>{
        const m=q('[data-role="recovery-message"]'),recoveryCode=q('[data-role="recovery-code"]')?.value||'';
        try{const r=await json(this.api('guest-identity.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'recover',clientId,recoveryCode})});await this.refresh(r.message||'Guest history recovered.')}catch(e){if(m)m.textContent=e.message}
      });
      q('[data-act="logout"]')?.addEventListener('click',async()=>{
        try{await json(this.api('account.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout',clientId})});await this.refresh('Signed out.')}catch(e){q('[data-role="main-message"]').textContent=e.message}
      });
    }
  }

  window.LoomGlobalProfile=Object.freeze({
    create(opts){return new GlobalProfile(opts)}
  });
})();