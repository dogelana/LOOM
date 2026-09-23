// @loom-file release=0.15.00 revision=10 policy=package-priority
(()=>{
  const VERSION=String(window.LoomConfig?.engineVersion||'unknown');
  const HERO_X=-35.26438968,HERO_Y=315,HOLD_MS=3000,SPIN_MS=7000,LOOP_MS=10000;
  const ease=t=>t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;
  const motionState=ms=>{const c=Math.max(0,Math.min(LOOP_MS,ms));if(c<HOLD_MS)return{active:false,p:0,e:0,env:0,env2:0,fluid:0,fluid2:0,fluid3:0,whip:0};const p=Math.min(1,(c-HOLD_MS)/SPIN_MS),e=ease(p),env=Math.sin(Math.PI*p);return{active:true,p,e,env,env2:env*env,fluid:Math.sin(2*Math.PI*p),fluid2:Math.sin(4*Math.PI*p),fluid3:Math.cos(2*Math.PI*p),whip:env*(1-p)}};
  const base=()=>({rx:HERO_X,ry:HERO_Y,rz:0,sx:1,sy:1,sz:1});
  const PRESETS=[
    {id:'hero-orbit',name:'Hero Orbit',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e;p.rx=HERO_X+1.15*s.env*s.fluid;p.rz=.65*s.env*s.fluid;p.sx=1+.028*s.env*s.fluid;p.sy=1-.022*s.env*s.fluid;p.sz=1+.018*s.env*s.fluid}return p}},
    {id:'comet-sweep',name:'Comet Sweep',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e+14*s.whip;p.rx=HERO_X+8.5*s.env2-5.5*s.env*s.fluid;p.rz=-7.5*s.env*s.fluid+3.5*s.env*s.fluid2;p.sx=1+.030*s.env2;p.sy=1-.020*s.env*s.fluid3;p.sz=1+.024*s.env*s.fluid}return p}},
    {id:'tidal-arc',name:'Tidal Arc',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e-10*s.env*s.fluid3;p.rx=HERO_X-10*s.env2+3*s.env*s.fluid2;p.rz=6*s.env*s.fluid+2.5*s.env*s.fluid2;p.sx=1+.012*s.env*s.fluid;p.sy=1-.028*s.env2;p.sz=1+.018*s.env2}return p}},
    {id:'prism-roll',name:'Prism Roll',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e;p.rx=HERO_X+4.5*s.env*s.fluid2;p.rz=12*s.env2*Math.sin(Math.PI*s.p);p.sx=1+.020*s.env*s.fluid2;p.sy=1-.020*s.env*s.fluid2;p.sz=1+.014*s.env2}return p}},
    {id:'halo-drift',name:'Halo Drift',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e+8*s.env*s.fluid;p.rx=HERO_X+12*s.env2-4*s.env*s.fluid;p.rz=-9*s.env2+4*s.env*s.fluid2;p.sx=1+.014*s.env2;p.sy=1-.026*s.env2;p.sz=1+.020*s.env*s.fluid3}return p}},
    {id:'zenith-dive',name:'Zenith Dive',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e;p.rx=HERO_X-18*s.env2+2.5*s.env*s.fluid2;p.rz=8.5*s.env*s.fluid-2*s.env*s.fluid2;p.sx=1+.026*s.env2;p.sy=1-.030*s.env2;p.sz=1+.020*s.env*s.fluid}return p}},
    {id:'gyro-bloom',name:'Gyro Bloom',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+720*s.e;p.rx=HERO_X+6*s.env*s.fluid+2*s.env*s.fluid2;p.rz=5*s.env*s.fluid2;p.sx=1+.022*s.env*s.fluid3;p.sy=1-.018*s.env*s.fluid;p.sz=1+.022*s.env2}return p}},
    {id:'meteor-bank',name:'Meteor Bank',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e+18*s.whip;p.rx=HERO_X+5*s.env*s.fluid2-4*s.env2;p.rz=-14*s.env2+4*s.env*s.fluid;p.sx=1+.024*s.env2;p.sy=1-.018*s.env*s.fluid3;p.sz=1+.020*s.env*s.fluid}return p}},
    {id:'ribbon-spiral',name:'Ribbon Spiral',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+540*s.e;p.rx=HERO_X+8*s.env2*Math.sin(2*Math.PI*s.p);p.rz=10*s.env2*Math.sin(2*Math.PI*s.p+Math.PI/3);p.sx=1+.018*s.env2;p.sy=1-.022*s.env*s.fluid;p.sz=1+.018*s.env*s.fluid2}return p}},
    {id:'pulse-carousel',name:'Pulse Carousel',pose(ms){const s=motionState(ms),p=base();if(s.active){p.ry=HERO_Y+360*s.e-6*s.env*s.fluid3;p.rx=HERO_X+4*s.env*s.fluid;p.rz=7*s.env*s.fluid2;const q=Math.sin(Math.PI*s.p);p.sx=1+.032*q*q;p.sy=1-.028*q*q;p.sz=1+.022*q*q}return p}}
  ];
  const preset=id=>PRESETS.find(x=>x.id===id)||PRESETS[0];

  function injectStyle(){if(document.getElementById('loom-brand-core-style'))return;const s=document.createElement('style');s.id='loom-brand-core-style';s.textContent=`
    .loom-cube-host{--loom-cube-size:96px;--loom-cube-half:calc(var(--loom-cube-size)/2);position:relative;perspective:calc(var(--loom-cube-size)*3.05);display:grid;place-items:center;isolation:isolate;overflow:visible}.loom-cube-host.loom-cube-host-size-to-content{width:var(--loom-cube-size);height:var(--loom-cube-size)}
    .loom-cube{position:relative;width:var(--loom-cube-size);height:var(--loom-cube-size);transform-style:preserve-3d;transform-origin:50% 50%;will-change:transform}
    .loom-cube-face{position:absolute;inset:0;width:var(--loom-cube-size);height:var(--loom-cube-size);background:var(--loom-cube-face,#fff);backface-visibility:hidden;-webkit-backface-visibility:hidden;transform-style:preserve-3d;overflow:visible;box-shadow:inset 0 0 0 1px rgba(0,0,0,.025)}
    .loom-cube-face svg{display:block;width:100%;height:100%;shape-rendering:crispEdges;overflow:visible}.loom-cube-front{transform:translateZ(var(--loom-cube-half))}.loom-cube-back{transform:rotateY(180deg) translateZ(var(--loom-cube-half))}.loom-cube-right{transform:rotateY(90deg) translateZ(var(--loom-cube-half))}.loom-cube-left{transform:rotateY(-90deg) translateZ(var(--loom-cube-half))}.loom-cube-top{transform:rotateX(90deg) translateZ(var(--loom-cube-half))}.loom-cube-bottom{transform:rotateX(-90deg) translateZ(var(--loom-cube-half))}.loom-cube-mirror-x{transform:scaleX(-1);transform-origin:50% 50%}.loom-cube-mirror-y{transform:scaleY(-1);transform-origin:50% 50%}
    .loom-powered{display:inline-flex;align-items:center;gap:7px;color:inherit}.loom-powered img{width:18px;height:18px;object-fit:contain}.loom-brand-version{font:850 10px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.02em}
    .loom-shell-chrome-header{width:100%;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:11px max(16px,calc((100vw - 1240px)/2));border-bottom:1px solid rgba(194,214,199,.78);background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(248,252,249,.88));backdrop-filter:blur(22px) saturate(130%);-webkit-backdrop-filter:blur(22px) saturate(130%);position:relative;z-index:500;box-sizing:border-box;box-shadow:0 10px 34px rgba(21,63,32,.06),inset 0 1px rgba(255,255,255,.9)}
    .loom-shell-chrome-brand{display:flex;align-items:center;gap:11px;min-width:0}.loom-shell-chrome-cube{width:58px;height:58px;flex:0 0 58px;display:grid;place-items:center;padding:11px;overflow:hidden;border:1px solid rgba(201,218,205,.72);border-radius:17px;background:linear-gradient(145deg,rgba(255,255,255,.92),rgba(243,249,245,.88));box-shadow:0 8px 24px rgba(22,68,35,.07),inset 0 1px #fff}.loom-shell-chrome-text{min-width:0}.loom-shell-chrome-title{font:950 13px/1.05 Inter,system-ui;color:#183522;letter-spacing:-.018em}.loom-shell-chrome-sub{margin-top:4px;font:800 9px/1.1 Inter,system-ui;color:#77867c;letter-spacing:.08em;text-transform:uppercase}
    .loom-shell-chrome-links{display:flex;align-items:center;gap:7px;flex-wrap:wrap;justify-content:flex-end}.loom-shell-chrome-links a,.loom-shell-chrome-links button{min-height:36px;padding:8px 11px;border:1px solid #d6e4d9;border-radius:11px;background:linear-gradient(180deg,#fff,#f6faf7);color:#345c40;text-decoration:none;font:900 10px/1 Inter,system-ui;cursor:pointer;box-shadow:0 4px 14px rgba(22,68,35,.045);transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease}.loom-shell-chrome-links a:hover,.loom-shell-chrome-links button:hover{transform:translateY(-1px);border-color:#bcd7c3;box-shadow:0 8px 20px rgba(22,68,35,.08)}
    .loom-shell-chrome-footer{width:min(1240px,calc(100% - 28px));margin:28px auto 18px;padding:14px 18px;border:1px solid rgba(205,221,209,.88);border-radius:22px;background:linear-gradient(145deg,rgba(255,255,255,.88),rgba(245,250,247,.84));backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);box-shadow:0 18px 52px rgba(20,62,31,.075),inset 0 1px #fff;display:flex;align-items:center;justify-content:center;gap:12px;color:#526359;box-sizing:border-box}
    .loom-shell-footer-cube{width:50px;height:50px;display:grid;place-items:center;flex:0 0 50px;padding:8px;overflow:hidden;border-radius:12px}.loom-shell-footer-copy{display:flex;flex-direction:column;gap:3px}.loom-shell-footer-copy strong{font:950 12px/1 Inter,system-ui;color:#173722}.loom-shell-footer-copy span{font:850 9px/1.1 Inter,system-ui;color:#77857c}
    @media(max-width:680px){.loom-shell-chrome-header{align-items:flex-start;padding:9px 10px}.loom-shell-chrome-sub{display:none}.loom-shell-chrome-links a{font-size:9px;padding:7px}.loom-shell-chrome-footer{width:calc(100% - 18px)}}
  `;document.head.appendChild(s)}
  const lo=`<g transform="translate(.25 .25) scale(.9)"><rect x="0" y="0" width="1" height="5"/><rect x="0" y="4" width="5" height="1"/><rect x="2" y="0" width="3" height="1"/><rect x="2" y="1" width="1" height="1"/><rect x="4" y="1" width="1" height="1"/><rect x="2" y="2" width="3" height="1"/></g>`;
  const mm=`<g transform="translate(.25 .25) scale(.9)"><rect x="0" y="0" width="5" height="1"/><rect x="0" y="0" width="1" height="5"/><rect x="2" y="0" width="1" height="5"/><rect x="4" y="0" width="1" height="5"/></g>`;
  function face(cls,inner,svgClass=''){return `<div class="loom-cube-face ${cls}"><svg ${svgClass?`class="${svgClass}"`:''} viewBox="0 0 5 5" aria-hidden="true" fill="currentColor">${inner}</svg></div>`}
  function cubeMarkup(){return `<div class="loom-cube" aria-hidden="true">${face('loom-cube-front',lo)}${face('loom-cube-back',lo,'loom-cube-mirror-x')}${face('loom-cube-right',mm)}${face('loom-cube-left',mm,'loom-cube-mirror-x')}${face('loom-cube-top',`<g transform="rotate(90 2.5 2.5)">${lo}</g>`)}${face('loom-cube-bottom',`<g transform="rotate(90 2.5 2.5)">${lo}</g>`,'loom-cube-mirror-y')}</div>`}

  function mountCube(host,opts={}){injectStyle();if(!host)return()=>{};const size=Math.max(24,Number(opts.size||96)),path=String(opts.path||'hero-orbit'),speed=Math.max(.1,Number(opts.speed||1)),animate=opts.animate!==false&&!matchMedia('(prefers-reduced-motion: reduce)').matches;host.classList.add('loom-cube-host');if(opts.sizeHost===true)host.classList.add('loom-cube-host-size-to-content');else host.classList.remove('loom-cube-host-size-to-content');host.style.setProperty('--loom-cube-size',`${size}px`);host.style.color=opts.color||'#050505';host.style.setProperty('--loom-cube-face',opts.faceColor||'#fff');host.innerHTML=cubeMarkup();const cube=host.querySelector('.loom-cube');let raf=0,start=performance.now();const p=preset(path);function frame(now){const t=((now-start)*speed)%LOOP_MS,q=p.pose(t);cube.style.transform=`rotateX(${q.rx}deg) rotateY(${q.ry}deg) rotateZ(${q.rz}deg) scale3d(${q.sx},${q.sy},${q.sz})`;raf=requestAnimationFrame(frame)}if(animate)raf=requestAnimationFrame(frame);else{const q=p.pose(0);cube.style.transform=`rotateX(${q.rx}deg) rotateY(${q.ry}deg) rotateZ(${q.rz}deg)`}return()=>{if(raf)cancelAnimationFrame(raf);host.innerHTML='';host.classList.remove('loom-cube-host');host.classList.remove('loom-cube-host-size-to-content')}}
  async function fetchSettings(apiBase='api'){try{const base=String(apiBase||'api').replace(/\/$/,'');const r=await fetch(`${base}/global-settings.php?_=${Date.now()}`,{cache:'no-store'});if(!r.ok)throw 0;return await r.json()}catch{return{ok:false,settings:{'loom.brand.motion':{animationPath:'hero-orbit',animationSpeedPercent:100,animationEnabled:'on'},'loom.loader.experience':{showTips:'on',tipIntervalSeconds:4,minimumVisibleMs:320},'loom.home.update-log':{maxReleases:12,expandedReleases:1}}}}}
  async function fetchRelease(apiBase='api'){try{const base=String(apiBase||'api').replace(/\/$/,'');const r=await fetch(`${base}/version.php?_=${Date.now()}`,{cache:'no-store'});if(!r.ok)throw 0;return await r.json()}catch{return{ok:false,canonicalVersion:VERSION,health:{status:'unknown'}}}}
  function brandConfig(data){const x=data?.settings?.['loom.brand.motion']||{},enabled=data?.moduleStates?.['loom.brand.motion']?.enabled!==false;return{path:x.animationPath||'hero-orbit',speed:Math.max(.1,Number(x.animationSpeedPercent||100)/100),animate:enabled&&x.animationEnabled!=='off'}}

  async function mountShellHeader(host,opts={}){
    injectStyle();if(!host)return()=>{};
    const settings=opts.settings||await fetchSettings(opts.apiBase||'api');
    const bc=brandConfig(settings);
    host.className='loom-shell-chrome-header';
    host.innerHTML=`<div class="loom-shell-chrome-brand"><div class="loom-shell-chrome-cube"></div><div class="loom-shell-chrome-text"><div class="loom-shell-chrome-title">LOOM</div><div class="loom-shell-chrome-sub">${String(opts.pageTitle||'Application Engine').replace(/[<>]/g,'')}</div></div></div><nav class="loom-shell-chrome-links"></nav>`;
    const stop=mountCube(host.querySelector('.loom-shell-chrome-cube'),{size:20,path:bc.path,speed:bc.speed,animate:bc.animate});
    const nav=host.querySelector('nav');
    for(const link of opts.links||[]){
      if(!link?.href||!link?.label)continue;
      const a=document.createElement('a');a.href=link.href;a.textContent=link.label;if(link.target)a.target=link.target;nav.appendChild(a);
    }
    return stop;
  }

  async function mountShellFooter(host,opts={}){
    injectStyle();if(!host)return()=>{};
    const settings=opts.settings||await fetchSettings(opts.apiBase||'api');
    const release=await fetchRelease(opts.apiBase||'api');
    const shownVersion=release?.canonicalVersion||VERSION;
    const bc=brandConfig(settings);
    host.className='loom-shell-chrome-footer';
    host.innerHTML=`<div class="loom-shell-footer-cube"></div><div class="loom-shell-footer-copy"><strong>Powered by LOOM</strong><span>LOOM v${shownVersion} · © 2026 LOOM</span></div>`;
    return mountCube(host.querySelector('.loom-shell-footer-cube'),{size:22,path:bc.path,speed:bc.speed,animate:bc.animate});
  }

  window.LoomBrand=Object.freeze({
    version:VERSION,
    motionPresets:PRESETS.map(({id,name})=>({id,name})),
    mountCube,fetchSettings,fetchRelease,brandConfig,mountShellHeader,mountShellFooter,
    staticLogoUrl:'assets/loom-logo.png'
  });
})();
