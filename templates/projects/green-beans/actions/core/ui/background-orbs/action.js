// @loom-file release=0.12.08 revision=3 policy=package-priority
export async function createModule(ctx){
  let layer=null;
  let style=null;
  let animations=[];
  let raisedShell=null;
  let specialTimer=null;
  let specialNode=null;
  let specialCleanup=null;
  let specialMotion=null;

  const LOOM_DEFAULT_GLOW='#8FA8FF';
  const adminOverrides=ctx.descriptor?.admin_overrides||{};
  const clamp=(v,min,max)=>Math.max(min,Math.min(max,Number(v)||0));
  const sourceMode=String(ctx.config.sourceMode||'project');
  const orbVolume=Math.round(clamp(ctx.config.orbVolume??70,0,180));
  const speed=clamp(ctx.config.speed??100,20,250);
  const glowLevel=clamp(ctx.config.glowLevel??58,0,100);
  const specialEnabled=ctx.config.specialOrbEnabled!==false&&String(ctx.config.specialOrbEnabled||'on')!=='off';
  const specialIntervalSeconds=clamp(ctx.config.specialOrbIntervalSeconds??42,12,180);
  const specialDurationSeconds=clamp(ctx.config.specialOrbDurationSeconds??14,5,36);
  const specialSize=clamp(ctx.config.specialOrbSize??48,24,110);

  function rgba(hex,alpha){
    const m=String(hex||'').trim().match(/^#([0-9a-f]{6})$/i);
    if(!m)return `rgba(143,168,255,${alpha})`;
    const n=parseInt(m[1],16);
    return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${alpha})`;
  }
  function rand(min,max){return min+Math.random()*(max-min)}
  function coin(){return Math.random()>.5?1:-1}
  function pick(list){return list[Math.floor(Math.random()*list.length)]}
  function reducedMotion(){return Boolean(matchMedia?.('(prefers-reduced-motion: reduce)')?.matches)}

  async function resolveProvider(){
    const provider=ctx.getExtension('core.ui.background-orbs.provider');
    const useProject=sourceMode==='project'&&provider;
    let assetUrl=null;
    let emoji=null;
    let specialAssetUrl=null;
    let specialEmoji=null;

    if(useProject&&typeof provider.getOrbEmoji==='function'){
      try{emoji=String(await provider.getOrbEmoji()||'').trim()||null}catch{}
    }else if(useProject&&provider.orbEmoji){
      emoji=String(provider.orbEmoji||'').trim()||null;
    }

    if(!emoji){
      if(useProject&&typeof provider.getOrbAssetUrl==='function'){
        try{assetUrl=await provider.getOrbAssetUrl()}catch{}
      }else if(useProject&&provider.orbAssetUrl){
        assetUrl=provider.orbAssetUrl;
      }
    }

    if(useProject&&typeof provider.getSpecialOrbEmoji==='function'){
      try{specialEmoji=String(await provider.getSpecialOrbEmoji()||'').trim()||null}catch{}
    }else if(useProject&&provider.specialOrbEmoji){
      specialEmoji=String(provider.specialOrbEmoji||'').trim()||null;
    }

    if(!specialEmoji&&useProject){
      if(typeof provider.getSpecialOrbAssetUrl==='function'){
        try{specialAssetUrl=await provider.getSpecialOrbAssetUrl()}catch{}
      }else if(provider.specialOrbAssetUrl){
        specialAssetUrl=provider.specialOrbAssetUrl;
      }
    }

    const customGlow=Object.prototype.hasOwnProperty.call(adminOverrides,'glowColor');
    const glowColor=customGlow
      ? String(ctx.config.glowColor||LOOM_DEFAULT_GLOW)
      : (useProject&&provider?.glowColor ? String(provider.glowColor) : String(ctx.config.glowColor||LOOM_DEFAULT_GLOW));
    const specialGlowColor=useProject&&provider?.specialGlowColor
      ? String(provider.specialGlowColor)
      : glowColor;

    return {
      provider,
      useProject:Boolean(useProject),
      assetUrl,
      emoji,
      glowColor,
      specialAssetUrl,
      specialEmoji,
      specialGlowColor,
      specialKind:useProject&&provider?.specialOrbKind ? String(provider.specialOrbKind) : 'loom-cube'
    };
  }

  function injectStyle(){
    if(style)return;
    style=document.createElement('style');
    style.dataset.loomModule=ctx.action.id;
    style.textContent=`
      .loom-background-orbs{
        position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0;
        contain:strict;isolation:isolate;user-select:none;
      }
      .loom-background-orb{
        position:absolute;display:block;pointer-events:none;will-change:transform,opacity;
        transform-origin:center center;
      }
      .loom-background-orb.is-loom{
        background:linear-gradient(90deg,transparent 0%,var(--energy-color) 28%,rgba(255,255,255,.96) 52%,var(--energy-color) 72%,transparent 100%);
        border:0!important;border-radius:999px;
        box-shadow:0 0 var(--energy-blur) var(--energy-glow),0 0 calc(var(--energy-blur)*2.1) var(--energy-glow-soft);
        filter:none!important;
      }
      .loom-background-orb.is-loom.energy-node{
        border-radius:2px;
        transform:rotate(45deg);
        background:linear-gradient(135deg,var(--energy-color),rgba(255,255,255,.98));
      }
      .loom-background-orb.is-loom::after{
        content:"";position:absolute;left:50%;top:50%;width:2px;height:2px;transform:translate(-50%,-50%);
        background:white;border-radius:50%;box-shadow:0 0 7px var(--energy-color);
      }
      .loom-background-orb.is-project{object-fit:contain}
      .loom-background-orb.is-project-emoji,
      .loom-background-special.is-special-emoji{
        display:grid;place-items:center;width:auto!important;height:auto!important;
        background:transparent!important;border:0!important;
        font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif;
        line-height:1;text-rendering:optimizeLegibility;
      }
      .loom-background-special{
        position:absolute;display:grid;place-items:center;pointer-events:none;
        will-change:transform,opacity;transform-origin:center center;z-index:1;
      }
      .loom-background-special.is-special-image{object-fit:contain}
      .loom-background-special.is-special-cube{overflow:visible}
      body>.shell{position:relative;z-index:1}
      @media(prefers-reduced-motion:reduce){.loom-background-orb,.loom-background-special{animation:none!important}}
    `;
    document.head.appendChild(style);
  }

  function makeMotion(node,index,{special=false}={}){
    if(reducedMotion())return null;
    const spanX=special?rand(90,260):rand(45,180);
    const spanY=special?rand(70,190):rand(32,135);
    const x1=coin()*spanX,y1=coin()*spanY,x2=coin()*rand(25,spanX),y2=coin()*rand(20,spanY);
    const r1=rand(-28,28),r2=rand(-70,70);
    const scale1=rand(.82,1.12),scale2=rand(.86,1.18);
    const baseDuration=special?specialDurationSeconds*1000:rand(42000,96000);
    const duration=special?baseDuration:baseDuration/(speed/100);
    const delay=special?0:-Math.random()*duration;
    const frames=special?[
      {transform:'translate3d(0,0,0) rotate(0deg) scale(.78)',opacity:0},
      {transform:`translate3d(${x1*.18}px,${y2*.18}px,0) rotate(${r1*.25}deg) scale(1)`,opacity:.82,offset:.16},
      {transform:`translate3d(${x1*.65}px,${y1*.55}px,0) rotate(${r1}deg) scale(1.08)`,opacity:.66,offset:.56},
      {transform:`translate3d(${x2}px,${y2}px,0) rotate(${r2}deg) scale(.9)`,opacity:0}
    ]:[
      {transform:'translate3d(0,0,0) rotate(0deg) scale(.92)',opacity:.12},
      {transform:`translate3d(${x1*.48}px,${y2*.5}px,0) rotate(${r1*.45}deg) scale(${scale1})`,opacity:.72,offset:.27},
      {transform:`translate3d(${x1}px,${y1}px,0) rotate(${r1}deg) scale(${scale2})`,opacity:.34,offset:.52},
      {transform:`translate3d(${x2}px,${y2}px,0) rotate(${r2}deg) scale(${(scale1+scale2)/2})`,opacity:.65,offset:.78},
      {transform:`translate3d(${x2*.18}px,${-y1*.22}px,0) rotate(${r2*.18}deg) scale(.94)`,opacity:.16}
    ];
    const animation=node.animate(frames,{duration,delay,iterations:special?1:Infinity,direction:special?'normal':'alternate',easing:'ease-in-out',fill:special?'forwards':'none'});
    if(!special)animations.push(animation);
    return animation;
  }

  function addOrb({assetUrl,emoji,glowColor},index){
    const node=emoji
      ? document.createElement('span')
      : (assetUrl?document.createElement('img'):document.createElement('div'));

    const isLoom=!assetUrl&&!emoji;
    node.className=`loom-background-orb ${emoji?'is-project-emoji':(assetUrl?'is-project':'is-loom')}`;
    node.setAttribute('aria-hidden','true');

    if(emoji){
      node.textContent=emoji;
      const size=rand(18,44);
      node.style.fontSize=`${size}px`;
    }else if(assetUrl){
      node.src=assetUrl;node.alt='';
      const size=rand(18,46);
      node.style.width=`${size}px`;node.style.height=`${size}px`;
    }else{
      const variant=Math.random();
      if(variant<.16){
        node.classList.add('energy-node');
        const size=rand(2.2,5.2);
        node.style.width=`${size}px`;node.style.height=`${size}px`;
      }else{
        const length=variant<.58?rand(7,18):rand(15,34);
        const thickness=rand(1.1,2.7);
        node.style.width=`${length}px`;node.style.height=`${thickness}px`;
      }
    }

    node.style.left=`${rand(-2,98)}%`;
    node.style.top=`${rand(-3,98)}%`;
    node.style.opacity=String(isLoom?rand(.18,.62):rand(.18,.48));

    const glowAlpha=(glowLevel/100)*.74;
    const blur=Math.round(3+(glowLevel/100)*13);
    node.style.setProperty('--energy-color',glowColor);
    node.style.setProperty('--energy-glow',rgba(glowColor,glowAlpha));
    node.style.setProperty('--energy-glow-soft',rgba(glowColor,glowAlpha*.35));
    node.style.setProperty('--energy-blur',`${blur}px`);

    if(!isLoom){
      node.style.filter=`drop-shadow(0 0 ${blur}px ${rgba(glowColor,glowAlpha*.72)})`;
    }

    layer.appendChild(node);
    makeMotion(node,index);
  }

  function clearSpecial(){
    try{specialMotion?.cancel()}catch{}specialMotion=null;
    specialCleanup?.();specialCleanup=null;
    specialNode?.remove();specialNode=null;
  }

  function scheduleSpecial(resolved,first=false){
    if(!specialEnabled||reducedMotion())return;
    clearTimeout(specialTimer);
    const base=specialIntervalSeconds*1000;
    const delay=first?rand(base*.28,base*.72):rand(base*.72,base*1.28);
    specialTimer=setTimeout(()=>spawnSpecial(resolved),delay);
  }

  function spawnSpecial(resolved){
    if(!layer||!specialEnabled)return;
    clearSpecial();

    const specialEmoji=resolved.specialEmoji;
    const specialAssetUrl=resolved.specialAssetUrl;
    const useCube=!specialEmoji&&!specialAssetUrl;
    const node=specialEmoji
      ? document.createElement('span')
      : (specialAssetUrl?document.createElement('img'):document.createElement('div'));

    node.className=`loom-background-special ${specialEmoji?'is-special-emoji':(specialAssetUrl?'is-special-image':'is-special-cube')}`;
    node.setAttribute('aria-hidden','true');
    node.style.left=`${rand(5,90)}%`;
    node.style.top=`${rand(7,86)}%`;
    node.style.opacity='0';
    const glow=resolved.specialGlowColor||resolved.glowColor;
    const blur=Math.round(10+(glowLevel/100)*18);

    if(specialEmoji){
      node.textContent=specialEmoji;
      node.style.fontSize=`${specialSize}px`;
      node.style.filter=`drop-shadow(0 0 ${blur}px ${rgba(glow,.72)})`;
    }else if(specialAssetUrl){
      node.src=specialAssetUrl;node.alt='';
      node.style.width=node.style.height=`${specialSize}px`;
      node.style.filter=`drop-shadow(0 0 ${blur}px ${rgba(glow,.72)})`;
    }else{
      node.style.width=node.style.height=`${specialSize}px`;
    }

    layer.appendChild(node);
    specialNode=node;

    if(useCube&&window.LoomBrand?.mountCube){
      const presets=(window.LoomBrand.motionPresets||[]).map(x=>x.id).filter(Boolean);
      const path=presets.length?pick(presets):'hero-orbit';
      const cubeSize=Math.max(24,Math.round(specialSize*.76));
      const cubeHost=document.createElement('div');
      cubeHost.style.width=cubeHost.style.height=`${specialSize}px`;
      cubeHost.style.display='grid';cubeHost.style.placeItems='center';cubeHost.style.overflow='visible';
      node.appendChild(cubeHost);
      specialCleanup=window.LoomBrand.mountCube(cubeHost,{
        size:cubeSize,
        sizeHost:true,
        path,
        speed:rand(.62,1.42),
        animate:true,
        color:glow,
        faceColor:'#F9FBFF'
      });
      node.style.filter=`drop-shadow(0 0 ${blur}px ${rgba(glow,.7)})`;
    }

    const anim=makeMotion(node,0,{special:true});
    specialMotion=anim;
    const done=()=>{
      clearSpecial();
      scheduleSpecial(resolved,false);
    };
    if(anim){anim.addEventListener('finish',done,{once:true})}
    else{specialTimer=setTimeout(done,specialDurationSeconds*1000)}
  }

  async function remove(reason='deactivate'){
    clearTimeout(specialTimer);specialTimer=null;
    clearSpecial();
    animations.forEach(a=>{try{a.cancel()}catch{}});animations=[];
    layer?.remove();layer=null;
    style?.remove();style=null;
    if(raisedShell)raisedShell.style.zIndex=raisedShell.dataset.loomOrbOldZ||'';
    raisedShell=null;
    await ctx.log('background-orbs.unmounted',{reason});
  }

  return {
    async mount(){},
    async activate(){
      ctx.step('resolve-provider','active');
      const resolved=await resolveProvider();
      ctx.step('resolve-provider','completed',{
        requestedSource:sourceMode,
        effectiveSource:resolved.useProject?'project':'loom',
        providerLabel:resolved.provider?.label||null,
        projectAsset:Boolean(resolved.assetUrl),
        projectEmoji:resolved.emoji||null,
        specialType:resolved.specialEmoji?'emoji':(resolved.specialAssetUrl?'asset':'loom-cube'),
        glowColor:resolved.glowColor
      });

      injectStyle();
      ctx.step('create-field','active');
      layer=document.createElement('div');
      layer.className='loom-background-orbs';
      layer.dataset.module=ctx.action.id;
      layer.dataset.source=resolved.useProject?'project':'loom';
      document.body.prepend(layer);
      raisedShell=document.querySelector('body>.shell');
      if(raisedShell){raisedShell.dataset.loomOrbOldZ=raisedShell.style.zIndex||'';raisedShell.style.zIndex='1'}
      for(let i=0;i<orbVolume;i++)addOrb(resolved,i);
      ctx.step('create-field','completed',{orbVolume,visual:resolved.useProject?'project-provider':'loom-electric-energy'});
      ctx.step('animate-field','active');
      scheduleSpecial(resolved,true);
      ctx.step('animate-field','completed',{
        speed,glowLevel,motion:'infinite-zero-gravity-energy-field',
        specialOrb:specialEnabled,
        specialIntervalSeconds,
        specialDurationSeconds
      });
      await ctx.log('background-orbs.mounted',{
        orbVolume,speed,glowLevel,glowColor:resolved.glowColor,
        source:resolved.useProject?'project':'loom',
        provider:resolved.provider?.label||null,
        projectEmoji:resolved.emoji||null,
        visual:resolved.useProject?'project-provider':'loom-electric-energy',
        specialOrbEnabled:specialEnabled,
        specialType:resolved.specialEmoji?'emoji':(resolved.specialAssetUrl?'asset':'loom-cube')
      });
      return()=>remove('cleanup');
    },
    async deactivate(detail={}){await remove(detail.reason||'deactivate')},
    async unmount(detail={}){await remove(detail.reason||'unmount')}
  };
}
