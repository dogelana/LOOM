// @loom-file release=0.12.08 revision=2 policy=package-priority
export async function createModule(ctx){
  const assetUrl=name=>new URL(`./assets/${name}`,import.meta.url).href;

  const beanTypes=[
    {
      id:'string-bean',
      name:'String Bean',
      assets:{
        none:'string-bean.png',
        ballcap:'string-bean-ballcap.png'
      }
    },
    {
      id:'bean-pod',
      name:'Bean Pod',
      assets:{
        none:'bean-pod.png',
        ballcap:'bean-pod-ballcap.png'
      }
    }
  ];

  const headwearOptions=[
    {id:'none',name:'None',preview:'trait-none-x.png'},
    {id:'ballcap',name:'Ball Cap',preview:'trait-headwear-ballcap.png'}
  ];

  const defaultBackground='#EAF7E8';
  const imageCache=new Map();

  function loadImage(url){
    if(imageCache.has(url))return imageCache.get(url);
    const job=new Promise((resolve,reject)=>{
      const img=new Image();
      img.decoding='async';
      img.onload=()=>resolve(img);
      img.onerror=()=>reject(new Error('Could not load Green Beans avatar artwork.'));
      img.src=url;
    });
    imageCache.set(url,job);
    return job;
  }

  function resolveArtwork(typeId,headwearId){
    const type=beanTypes.find(x=>x.id===typeId)||beanTypes[0];
    const headwear=headwearOptions.some(x=>x.id===headwearId)?headwearId:'none';
    return {
      type,
      headwear,
      asset:type.assets[headwear]||type.assets.none
    };
  }

  async function drawAvatar(canvas,typeId,headwearId,background){
    const resolved=resolveArtwork(typeId,headwearId);
    const img=await loadImage(assetUrl(resolved.asset));
    const g=canvas.getContext('2d');
    const S=canvas.width;

    g.clearRect(0,0,S,S);
    g.fillStyle=background||defaultBackground;
    g.fillRect(0,0,S,S);

    const maxW=S*.78;
    const maxH=S*.91;
    const scale=Math.min(maxW/img.naturalWidth,maxH/img.naturalHeight);
    const w=img.naturalWidth*scale;
    const h=img.naturalHeight*scale;
    const x=(S-w)/2;
    const y=(S-h)/2-S*.005;
    g.drawImage(img,x,y,w,h);
  }

  async function blobFor(typeId,headwearId,background){
    const c=document.createElement('canvas');
    c.width=c.height=384;
    await drawAvatar(c,typeId,headwearId,background);
    let b=await new Promise(r=>c.toBlob(r,'image/webp',.84));
    if(!b)b=await new Promise(r=>c.toBlob(r,'image/png'));
    return b;
  }

  const extension={
    label:'Green Beans',
    userActionId:'user.avatar.green-beans.create',
    getDefaultAvatarUrl:async()=>assetUrl('default-avatar.png'),

    async openCreator({host,onApply,onCancel}){
      let selectedType='string-bean';
      let selectedHeadwear='none';
      let background=defaultBackground;

      host.innerHTML=`
        <div style="font-family:Inter,system-ui;color:#132019">
          <div style="font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#60806a">Green Beans avatar creator</div>
          <h2 style="margin:5px 0 4px;font-size:26px">Create your bean avatar</h2>
          <p style="margin:0 0 16px;color:#718078;font-size:11px;line-height:1.5">
            Choose a bean type, headwear, and background color.
          </p>

          <div style="display:grid;grid-template-columns:minmax(170px,220px) 1fr;gap:20px;align-items:start">
            <canvas data-preview width="384" height="384"
              style="width:100%;aspect-ratio:1;border-radius:50%;box-shadow:0 12px 38px rgba(23,68,34,.14);border:1px solid #dce8de;background:${defaultBackground}"></canvas>

            <div>
              <div style="font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#698071;margin-bottom:7px">Bean Type</div>
              <div data-types style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"></div>

              <div style="font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#698071;margin:18px 0 7px">Headwear</div>
              <div data-headwear style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"></div>

              <div style="font-size:9px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:#698071;margin:18px 0 7px">Background Color</div>
              <div style="display:flex;align-items:center;gap:10px">
                <input data-bg type="color" value="${defaultBackground}"
                  style="width:58px;height:42px;border:1px solid #d7e5da;border-radius:10px;padding:4px;background:#fff;cursor:pointer">
                <input data-bg-text type="text" value="${defaultBackground}" maxlength="7"
                  style="min-width:0;flex:1;padding:10px 11px;border:1px solid #d7e5da;border-radius:10px;font:800 11px/1.2 ui-monospace,monospace;color:#294532"
                  aria-label="Background color hex">
              </div>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
            <button data-cancel type="button"
              style="border:0;border-radius:10px;padding:10px 13px;background:#eef5ef;color:#385943;font-weight:900;cursor:pointer">Cancel</button>
            <button data-apply type="button"
              style="border:0;border-radius:10px;padding:10px 13px;background:#137c40;color:white;font-weight:900;cursor:pointer">Use Avatar</button>
          </div>
        </div>`;

      const preview=host.querySelector('[data-preview]');
      const typeList=host.querySelector('[data-types]');
      const headwearList=host.querySelector('[data-headwear]');
      const bg=host.querySelector('[data-bg]');
      const bgText=host.querySelector('[data-bg-text]');

      let paintTicket=0;
      const paint=async()=>{
        const ticket=++paintTicket;
        try{
          await drawAvatar(preview,selectedType,selectedHeadwear,background);
          if(ticket!==paintTicket)return;
        }catch{}
      };

      function syncButtons(list,key,value){
        [...list.children].forEach(btn=>{
          const active=btn.dataset[key]===value;
          btn.style.borderColor=active?'#43a95b':'#dbe7dd';
          btn.style.boxShadow=active?'0 0 0 3px rgba(50,173,80,.13)':'none';
          btn.style.background=active?'#f2fbf4':'#fff';
        });
      }

      function renderTypeButtons(){
        typeList.innerHTML='';
        for(const type of beanTypes){
          const btn=document.createElement('button');
          btn.type='button';
          btn.dataset.type=type.id;
          btn.innerHTML=`
            <img src="${assetUrl(type.assets.none)}" alt="" style="width:46px;height:58px;object-fit:contain;display:block;margin:auto">
            <span style="display:block;margin-top:4px">${type.name}</span>`;
          btn.style.cssText='border:1px solid #dbe7dd;border-radius:13px;background:#fff;padding:8px;text-align:center;font:900 10px/1.2 Inter,system-ui;cursor:pointer;color:#294532;transition:.15s ease';
          btn.onclick=()=>{
            selectedType=type.id;
            syncButtons(typeList,'type',selectedType);
            paint();
          };
          typeList.append(btn);
        }
        syncButtons(typeList,'type',selectedType);
      }

      function renderHeadwearButtons(){
        headwearList.innerHTML='';
        for(const hw of headwearOptions){
          const btn=document.createElement('button');
          btn.type='button';
          btn.dataset.headwear=hw.id;
          btn.innerHTML=`
            <img src="${assetUrl(hw.preview)}" alt="" style="width:56px;height:56px;object-fit:contain;display:block;margin:auto">
            <span style="display:block;margin-top:4px">${hw.name}</span>`;
          btn.style.cssText='border:1px solid #dbe7dd;border-radius:13px;background:#fff;padding:8px;text-align:center;font:900 10px/1.2 Inter,system-ui;cursor:pointer;color:#294532;transition:.15s ease';
          btn.onclick=()=>{
            selectedHeadwear=hw.id;
            syncButtons(headwearList,'headwear',selectedHeadwear);
            paint();
          };
          headwearList.append(btn);
        }
        syncButtons(headwearList,'headwear',selectedHeadwear);
      }

      const setBackground=value=>{
        const normalized=/^#[0-9a-f]{6}$/i.test(value)?value.toUpperCase():null;
        if(!normalized)return false;
        background=normalized;
        bg.value=normalized;
        bgText.value=normalized;
        paint();
        return true;
      };

      bg.addEventListener('input',()=>setBackground(bg.value));
      bgText.addEventListener('change',()=>{
        if(!setBackground(bgText.value.trim()))bgText.value=background;
      });
      bgText.addEventListener('keydown',e=>{
        if(e.key==='Enter'){
          e.preventDefault();
          if(!setBackground(bgText.value.trim()))bgText.value=background;
        }
      });

      renderTypeButtons();
      renderHeadwearButtons();
      await paint();

      host.querySelector('[data-cancel]').onclick=()=>onCancel?.();
      host.querySelector('[data-apply]').onclick=async()=>{
        const btn=host.querySelector('[data-apply]');
        btn.disabled=true;
        btn.textContent='Creating…';
        try{
          const blob=await blobFor(selectedType,selectedHeadwear,background);
          await onApply?.(blob,{
            project:'green-beans',
            beanType:selectedType,
            headwear:selectedHeadwear,
            backgroundColor:background
          });
        }finally{
          btn.disabled=false;
          btn.textContent='Use Avatar';
        }
      };
    }
  };

  return {
    extensions:{'core.user.profile.avatar':extension},
    async mount(){},
    async activate(){
      ctx.step('register-provider','active');
      ctx.step('register-provider','completed',{
        extension:'core.user.profile.avatar',
        default:true,
        creator:true,
        beanTypes:beanTypes.map(x=>x.id),
        headwear:headwearOptions.map(x=>x.id),
        backgroundColor:true
      });
      await ctx.log('green-beans.avatar-provider.ready',{
        beanTypes:beanTypes.length,
        headwearOptions:headwearOptions.length,
        backgroundColor:true,
        suppliedArtwork:true,
        traitPreviewIcons:true
      });
    },
    async deactivate(){},
    async unmount(){}
  };
}
