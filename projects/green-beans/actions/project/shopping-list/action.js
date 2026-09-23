// @loom-file release=0.12.08 revision=3 policy=package-priority
export async function createModule(ctx){
  let root=null,style=null,state={version:2,items:[],groups:[]},saving=null,reloadTimer=null;
  const moduleId='green-beans.shopping-list';
  const SHOP_EXTENSION='green-beans.shopping-list';
  const MEAL_EXTENSION='green-beans.meal-creator';
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const uid=p=>`${p}_${crypto?.randomUUID?.()||Date.now()+'_'+Math.random().toString(36).slice(2)}`;
  const now=()=>new Date().toISOString();
  const cleanText=s=>String(s??'').trim().replace(/\s+/g,' ').slice(0,160);
  const textKey=s=>cleanText(s).toLocaleLowerCase();
  const parseIngredients=s=>[...new Set(String(s??'').split(/[,\n]+/).map(cleanText).filter(Boolean).map(x=>x))];

  function normalize(raw){
    const groups=Array.isArray(raw?.groups)?raw.groups:[];
    const items=Array.isArray(raw?.items)?raw.items:[];
    const normalizedGroups=groups.map(g=>{
      const mealId=String(g?.mealId||'').trim();
      const kind=g?.kind==='meal'&&mealId?'meal':'manual';
      return {id:String(g?.id||uid('group')),name:cleanText(g?.name||'Group').slice(0,80)||'Group',kind,mealId:kind==='meal'?mealId:null,createdAt:g?.createdAt||now(),updatedAt:g?.updatedAt||g?.createdAt||now()};
    });
    const groupIds=new Set(normalizedGroups.filter(g=>g.kind==='manual').map(g=>g.id));
    const outItems=items.map(i=>({
      id:String(i?.id||uid('item')),
      text:cleanText(i?.text),
      completed:!!i?.completed,
      groupId:groupIds.has(String(i?.groupId||''))?String(i.groupId):null,
      mealIds:[...new Set((Array.isArray(i?.mealIds)?i.mealIds:[]).map(x=>String(x||'').trim()).filter(Boolean))],
      createdAt:i?.createdAt||now(),updatedAt:i?.updatedAt||i?.createdAt||now()
    })).filter(i=>i.text);
    return {version:2,groups:normalizedGroups,items:outItems};
  }

  async function api(action,payload={}){
    const r=await ctx.fetchApi('project-state.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,project:ctx.project,moduleId,clientId:ctx.identity.clientId,...payload})});
    const j=await r.json(); if(!r.ok||!j.ok)throw new Error(j.error||'Shopping list storage failed.'); return j;
  }
  async function load(){const j=await api('get');state=normalize(j.state||{});}
  function snapshot(){return JSON.parse(JSON.stringify(state));}
  function announce(reason='updated'){
    try{window.dispatchEvent(new CustomEvent('green-beans:shopping-list-changed',{detail:{reason,moduleId,count:state.items.length}}))}catch{}
  }
  async function persistNow(reason='saved'){
    clearTimeout(saving);saving=null;
    await api('save',{state});setStatus('Saved');announce(reason);return snapshot();
  }
  function save(reason='updated'){
    clearTimeout(saving);saving=setTimeout(()=>persistNow(reason).catch(e=>setStatus(e.message,true)),120);
  }
  function setStatus(t,bad=false){const n=root?.querySelector('[data-status]');if(n){n.textContent=t||'';n.style.color=bad?'#a33':'#63806c'}}
  function mealModulePresent(){return !!ctx.getModuleDescriptor?.('project.meal-creator')}
  function mealExtension(){return ctx.getExtension?.(MEAL_EXTENSION)||null}
  async function waitMealExtension(){for(let i=0;i<30;i++){const ext=mealExtension();if(ext)return ext;await new Promise(r=>setTimeout(r,60))}return null}

  function injectStyle(){if(style)return;style=document.createElement('style');style.textContent=`
    .gb-shop{width:100%;padding:24px;border:1px solid rgba(42,118,67,.18);border-radius:28px;background:rgba(255,255,255,.93);box-shadow:0 22px 70px rgba(19,72,37,.08);font-family:Inter,system-ui;color:#142119}.gbs-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.gbs-eyebrow{font-size:9px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#63806c}.gbs-head h2{margin:3px 0 4px;font-size:28px;letter-spacing:-.04em}.gbs-sub{margin:0;color:#718078;font-size:11px;max-width:760px}.gbs-add{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px;margin-top:16px}.gbs-add input{min-width:120px;border:2px solid #dce9df;border-radius:12px;padding:11px 12px;font:700 14px inherit;outline:none}.gbs-add input:focus{border-color:#3ab763;box-shadow:0 0 0 4px rgba(58,183,99,.1)}.gbs-btn{border:0;border-radius:10px;background:#137d41;color:#fff;padding:10px 13px;font-weight:900;font-size:10px;cursor:pointer}.gbs-btn.secondary{background:#eef6f0;color:#315e3e}.gbs-btn.meal{background:#234c2d;color:#fff;box-shadow:inset 0 0 0 1px rgba(255,255,255,.14)}.gbs-btn.danger{background:#fff0ee;color:#a33}.gbs-btn:disabled{opacity:.45;cursor:not-allowed}.gbs-shortcut{grid-column:1/-1;font-size:9px;color:#7a8b80;margin-top:-2px}.gbs-toolbar{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin:14px 0;padding:10px;border-radius:14px;background:#f5faf6;border:1px solid #e2ece4}.gbs-toolbar select{border:1px solid #d7e5da;border-radius:9px;padding:8px;background:#fff}.gbs-count{font-size:10px;color:#667d6c;font-weight:800;margin-left:auto}.gbs-group{margin-top:12px;border:1px solid #e0eae2;border-radius:17px;background:#fff;overflow:hidden}.gbs-group[data-kind="meal"]{border-color:#bfe0c6;box-shadow:0 8px 24px rgba(32,112,57,.05)}.gbs-group-head{display:flex;align-items:center;gap:9px;padding:11px 13px;background:#f7faf7;border-bottom:1px solid #e8efe9}.gbs-group[data-kind="meal"] .gbs-group-head{background:#eff8f1}.gbs-group-head strong{font-size:12px}.gbs-group-head span{font-size:9px;color:#718078}.gbs-meal-badge{padding:3px 7px;border-radius:999px;background:#dff1e3;color:#256b39;font-size:8px!important;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.gbs-group-edit{margin-left:auto;border:0;background:#fff;border-radius:8px;padding:5px 8px;font-size:8px;font-weight:900;color:#326143;cursor:pointer}.gbs-items{display:grid}.gbs-item{display:grid;grid-template-columns:auto auto 1fr auto;gap:9px;align-items:center;padding:10px 12px;border-bottom:1px solid #eef3ef}.gbs-item:last-child{border-bottom:0}.gbs-item.done .gbs-text{text-decoration:line-through;color:#829087}.gbs-select{accent-color:#739c7f}.gbs-check{width:18px;height:18px;accent-color:#2fa657}.gbs-text{font-size:12px;font-weight:750;min-width:0;word-break:break-word}.gbs-actions{display:flex;gap:5px}.gbs-actions button{border:0;background:#f1f6f2;border-radius:8px;padding:6px 8px;cursor:pointer;font-size:9px;font-weight:850;color:#486451}.gbs-empty{padding:18px;text-align:center;color:#87948b;font-size:11px}.gbs-overlay{position:fixed;inset:0;z-index:10020;background:rgba(8,26,14,.48);backdrop-filter:blur(5px);display:grid;place-items:center;padding:18px}.gbs-dialog{width:min(560px,100%);max-height:calc(100vh - 36px);overflow:auto;background:#fff;border-radius:22px;padding:20px;box-shadow:0 26px 90px rgba(8,38,17,.3)}.gbs-manage-row{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center;padding:9px 0;border-bottom:1px solid #edf2ee}.gbs-manage-row input{border:1px solid #d7e5da;border-radius:9px;padding:8px}.gbs-status{font-size:9px;color:#63806c;min-width:50px;text-align:right}@media(max-width:720px){.gbs-add{grid-template-columns:1fr 1fr}.gbs-add input{grid-column:1/-1}}@media(max-width:620px){.gb-shop{padding:15px}.gbs-toolbar{align-items:stretch}.gbs-count{margin-left:0}.gbs-item{grid-template-columns:auto auto 1fr}.gbs-actions{grid-column:3;justify-content:flex-end}.gbs-add{grid-template-columns:1fr}.gbs-add input,.gbs-shortcut{grid-column:1}}
  `;document.head.appendChild(style)}

  function mealGroups(){return state.groups.filter(g=>g.kind==='meal'&&g.mealId)}
  function manualGroups(){return state.groups.filter(g=>g.kind!=='meal')}
  function grouped(){
    const manual=new Map(manualGroups().map(g=>[g.id,{...g,items:[]}])) ;
    const meals=new Map(mealGroups().map(g=>[g.mealId,{...g,items:[]}])) ;
    const un={id:null,name:'Ungrouped',kind:'manual',mealId:null,items:[]};
    for(const i of state.items){let placed=false;const mg=i.groupId&&manual.get(i.groupId);if(mg){mg.items.push(i);placed=true}for(const mid of i.mealIds||[]){const g=meals.get(mid);if(g){g.items.push(i);placed=true}}if(!placed)un.items.push(i)}
    return [un,...mealGroups().map(g=>meals.get(g.mealId)),...manualGroups().map(g=>manual.get(g.id))].filter(g=>g&&((g.id!==null)||g.items.length));
  }

  function render(){if(!root)return;const hasMeals=mealModulePresent();root.innerHTML=`<div class="gbs-head"><div><div class="gbs-eyebrow">Green Beans</div><h2>Shopping List</h2><p class="gbs-sub">Type one ingredient or comma-separated ingredients. ${hasMeals?'Press <strong>Shift + Enter</strong> to turn the current ingredients into a meal instantly.':''}</p></div><div class="gbs-status" data-status></div></div><form class="gbs-add" data-add><input data-input autocomplete="off" placeholder="steak, eggs, green beans"><button class="gbs-btn" type="submit">Add Ingredient${hasMeals?'s':''}</button>${hasMeals?'<button class="gbs-btn meal" data-add-meal type="button">＋ Add as Meal</button><div class="gbs-shortcut">Enter = add ingredients · Shift + Enter = open Meal Creator with these ingredients</div>':''}</form><div class="gbs-toolbar"><span data-selected-count>0 selected</span><select data-group-target><option value="">Choose manual group…</option>${manualGroups().map(g=>`<option value="${esc(g.id)}">${esc(g.name)}</option>`).join('')}</select><button class="gbs-btn secondary" data-add-group type="button" disabled>Add to Group</button><button class="gbs-btn secondary" data-remove-group type="button" disabled>Remove from Group</button><button class="gbs-btn secondary" data-manage type="button">Manage Groups</button><span class="gbs-count">${state.items.filter(i=>i.completed).length}/${state.items.length} completed</span></div><div data-groups>${grouped().map(g=>`<section class="gbs-group" data-kind="${esc(g.kind||'manual')}"><div class="gbs-group-head">${g.id?`<input class="gbs-check" type="checkbox" data-group-check="${esc(g.id)}" data-group-kind="${esc(g.kind||'manual')}" data-meal-id="${esc(g.mealId||'')}" ${g.items.length&&g.items.every(i=>i.completed)?'checked':''}>`:''}<strong>${esc(g.name)}</strong>${g.kind==='meal'?'<span class="gbs-meal-badge">Meal</span>':''}<span>${g.items.length} item${g.items.length===1?'':'s'}</span>${g.kind==='meal'&&hasMeals?`<button class="gbs-group-edit" data-edit-meal="${esc(g.mealId)}" type="button">Edit Meal</button>`:''}</div><div class="gbs-items">${g.items.length?g.items.map(i=>`<div class="gbs-item ${i.completed?'done':''}" data-item="${esc(i.id)}"><input class="gbs-select" data-select value="${esc(i.id)}" type="checkbox"><input class="gbs-check" data-complete type="checkbox" ${i.completed?'checked':''}><div class="gbs-text">${esc(i.text)}</div><div class="gbs-actions"><button data-edit type="button">Edit</button><button data-delete type="button">Remove</button></div></div>`).join(''):'<div class="gbs-empty">No items in this group.</div>'}</div></section>`).join('')}</div>`;wire();queueMicrotask(()=>root.querySelector('[data-input]')?.focus())}

  function selectedIds(){return [...new Set([...root.querySelectorAll('[data-select]:checked')].map(x=>x.value))]}
  function selectionUi(){const ids=selectedIds(),target=root.querySelector('[data-group-target]');root.querySelector('[data-selected-count]').textContent=`${ids.length} selected`;root.querySelector('[data-add-group]').disabled=!ids.length||!target?.value;root.querySelector('[data-remove-group]').disabled=!ids.length}
  function mutate(action,fn,meta={},reason='updated'){return ctx.runUserAction(action,async()=>{fn();render();save(reason);return true},meta)}

  async function quickMeal(raw){const parts=parseIngredients(raw);if(!parts.length){setStatus('Type ingredients first',true);return}const ext=await waitMealExtension();if(!ext?.openCreate){setStatus('Meal Creator is still loading',true);return}await ext.openCreate({ingredientTexts:parts,source:'shopping-list'});const input=root?.querySelector('[data-input]');if(input){input.value='';input.focus()}}

  async function ensureItems(texts,{persist=true}={}){
    const ids=[];let changed=false;
    for(const raw of texts||[]){const text=cleanText(raw);if(!text)continue;const key=textKey(text);let item=state.items.find(i=>textKey(i.text)===key);if(!item){item={id:uid('item'),text,completed:false,groupId:null,mealIds:[],createdAt:now(),updatedAt:now()};state.items.push(item);changed=true}ids.push(item.id)}
    if(changed){render();if(persist)await persistNow('meal-ingredients-added')}
    return [...new Set(ids)];
  }

  async function syncMeal(meal,{persist=true}={}){
    const mealId=String(meal?.id||'').trim();if(!mealId)throw new Error('Meal ID is required.');
    const name=cleanText(meal?.name||'Meal').slice(0,80)||'Meal';
    const ingredientIds=[...new Set((meal?.ingredientIds||[]).map(String).filter(id=>state.items.some(i=>i.id===id)))];
    let group=state.groups.find(g=>g.kind==='meal'&&g.mealId===mealId);
    if(!group){group={id:`mealgroup_${mealId}`,name,kind:'meal',mealId,createdAt:now(),updatedAt:now()};state.groups.unshift(group)}else{group.name=name;group.updatedAt=now()}
    for(const item of state.items){const set=new Set(item.mealIds||[]);if(ingredientIds.includes(item.id))set.add(mealId);else set.delete(mealId);item.mealIds=[...set]}
    render();if(persist)await persistNow('meal-synced');return snapshot();
  }

  async function removeMeal(mealId,{persist=true}={}){
    mealId=String(mealId||'');state.groups=state.groups.filter(g=>!(g.kind==='meal'&&g.mealId===mealId));for(const item of state.items)item.mealIds=(item.mealIds||[]).filter(id=>id!==mealId);render();if(persist)await persistNow('meal-removed');return snapshot();
  }

  async function reconcileMeals(meals,{persist=true}={}){
    const list=Array.isArray(meals)?meals:[];const valid=new Set(list.map(m=>String(m.id||'')).filter(Boolean));
    state.groups=state.groups.filter(g=>g.kind!=='meal'||valid.has(g.mealId));for(const item of state.items)item.mealIds=(item.mealIds||[]).filter(id=>valid.has(id));
    for(const meal of list)await syncMeal(meal,{persist:false});
    render();if(persist)await persistNow('meals-reconciled');return snapshot();
  }

  function wire(){const form=root.querySelector('[data-add]'),input=root.querySelector('[data-input]');
    input.onkeydown=e=>{if(e.key==='Enter'&&e.shiftKey&&mealModulePresent()){e.preventDefault();e.stopPropagation();quickMeal(input.value)}};
    form.onsubmit=async e=>{e.preventDefault();const parts=parseIngredients(input.value);if(!parts.length)return;await mutate('user.shopping-list.item.add',()=>{for(const text of parts){if(state.items.some(i=>textKey(i.text)===textKey(text)))continue;state.items.push({id:uid('item'),text,completed:false,groupId:null,mealIds:[],createdAt:now(),updatedAt:now()})}},{count:parts.length},'ingredients-added');input.value='';input.focus()};
    root.querySelector('[data-add-meal]')?.addEventListener('click',()=>quickMeal(input.value));
    root.querySelectorAll('[data-select]').forEach(x=>x.onchange=selectionUi);root.querySelector('[data-group-target]').onchange=selectionUi;
    root.querySelectorAll('[data-item]').forEach(row=>{const id=row.dataset.item;row.querySelector('[data-complete]').onchange=e=>mutate('user.shopping-list.item.toggle',()=>{const i=state.items.find(x=>x.id===id);if(i){i.completed=e.target.checked;i.updatedAt=now()}},{itemId:id,completed:e.target.checked},'item-toggled');row.querySelector('[data-edit]').onclick=()=>{const i=state.items.find(x=>x.id===id);const v=prompt('Edit item',i?.text||'');if(v===null)return;const clean=cleanText(v);if(!clean)return;mutate('user.shopping-list.item.edit',()=>{i.text=clean;i.updatedAt=now()},{itemId:id},'item-edited')};row.querySelector('[data-delete]').onclick=async()=>{await mutate('user.shopping-list.item.remove',()=>{state.items=state.items.filter(x=>x.id!==id)},{itemId:id},'item-removed');const ext=mealExtension();if(ext?.removeShoppingItem)ext.removeShoppingItem(id).catch(()=>{})}});
    root.querySelectorAll('[data-group-check]').forEach(c=>c.onchange=e=>{const gid=c.dataset.groupCheck,kind=c.dataset.groupKind,mealId=c.dataset.mealId;mutate('user.shopping-list.group.toggle',()=>{for(const i of state.items){const belongs=kind==='meal'?(i.mealIds||[]).includes(mealId):i.groupId===gid;if(belongs){i.completed=e.target.checked;i.updatedAt=now()}}},{groupId:gid,mealId,completed:e.target.checked},'group-toggled')});
    root.querySelectorAll('[data-edit-meal]').forEach(b=>b.onclick=async()=>{const ext=await waitMealExtension();if(ext?.openEdit)await ext.openEdit(b.dataset.editMeal)});
    root.querySelector('[data-add-group]').onclick=()=>{const ids=selectedIds(),gid=root.querySelector('[data-group-target]').value;if(!ids.length||!gid)return;mutate('user.shopping-list.group.assign',()=>{for(const i of state.items)if(ids.includes(i.id)){i.groupId=gid;i.updatedAt=now()}},{count:ids.length,groupId:gid},'manual-group-assigned')};
    root.querySelector('[data-remove-group]').onclick=()=>{const ids=selectedIds();if(!ids.length)return;mutate('user.shopping-list.group.unassign',()=>{for(const i of state.items)if(ids.includes(i.id)){i.groupId=null;i.updatedAt=now()}},{count:ids.length},'manual-group-removed')};
    root.querySelector('[data-manage]').onclick=openGroupManager;selectionUi()}

  function openGroupManager(){const ov=document.createElement('div');ov.className='gbs-overlay';ov.innerHTML=`<div class="gbs-dialog"><div class="gbs-eyebrow">Shopping List</div><h2 style="margin:4px 0 6px">Manage Manual Groups</h2><p class="gbs-sub">Meal groups are owned by Meal Creator and are edited there.</p><form data-new style="display:flex;gap:8px;margin-top:12px"><input style="flex:1;border:1px solid #d7e5da;border-radius:9px;padding:9px" placeholder="New group name"><button class="gbs-btn">Create Group</button></form><div data-list style="margin-top:14px"></div><div style="text-align:right;margin-top:14px"><button class="gbs-btn secondary" data-close>Close</button></div></div>`;document.body.append(ov);const list=ov.querySelector('[data-list]');const redraw=()=>{const groups=manualGroups();list.innerHTML=groups.length?groups.map(g=>`<div class="gbs-manage-row" data-g="${esc(g.id)}"><input value="${esc(g.name)}"><span>${state.items.filter(i=>i.groupId===g.id).length} items</span><button class="gbs-btn danger" type="button">Delete</button></div>`).join(''):'<div class="gbs-empty">No manual groups yet.</div>';list.querySelectorAll('[data-g]').forEach(r=>{const gid=r.dataset.g,inp=r.querySelector('input');inp.onchange=()=>{const name=cleanText(inp.value);if(!name)return;mutate('user.shopping-list.group.rename',()=>{const g=state.groups.find(x=>x.id===gid&&x.kind!=='meal');if(g){g.name=name.slice(0,80);g.updatedAt=now()}},{groupId:gid},'group-renamed').then(redraw)};r.querySelector('button').onclick=()=>{if(!confirm('Delete this manual group? Its items will return to Ungrouped unless they belong to a meal.'))return;mutate('user.shopping-list.group.delete',()=>{state.groups=state.groups.filter(x=>x.id!==gid);for(const i of state.items)if(i.groupId===gid)i.groupId=null},{groupId:gid},'group-deleted').then(redraw)}})};ov.querySelector('[data-new]').onsubmit=e=>{e.preventDefault();const inp=e.currentTarget.querySelector('input'),name=cleanText(inp.value);if(!name)return;mutate('user.shopping-list.group.create',()=>state.groups.push({id:uid('group'),name:name.slice(0,80),kind:'manual',mealId:null,createdAt:now(),updatedAt:now()}),{name},'group-created').then(()=>{inp.value='';redraw()})};ov.querySelector('[data-close]').onclick=()=>ov.remove();ov.onclick=e=>{if(e.target===ov)ov.remove()};redraw()}

  const extension={
    id:SHOP_EXTENSION,
    getSnapshot:()=>snapshot(),
    getItems:()=>snapshot().items,
    ensureItems,
    syncMeal,
    removeMeal,
    reconcileMeals,
    async reload(){await load();render();return snapshot()}
  };

  const onExternalRefresh=()=>{clearTimeout(reloadTimer);reloadTimer=setTimeout(()=>load().then(render).catch(()=>{}),80)};
  async function remove(){clearTimeout(saving);clearTimeout(reloadTimer);window.removeEventListener('green-beans:shopping-list-refresh',onExternalRefresh);root?.remove();style?.remove();root=style=null}
  return {extensions:{[SHOP_EXTENSION]:extension},async mount(){},async activate(){injectStyle();ctx.step('load-state','active');try{await load();ctx.step('load-state','completed',{items:state.items.length,groups:state.groups.length})}catch(e){ctx.step('load-state','failed',{message:e.message})}root=document.createElement('section');root.className='gb-shop';root.dataset.module=ctx.action.id;ctx.mount(root);window.addEventListener('green-beans:shopping-list-refresh',onExternalRefresh);render();await ctx.log('shopping-list.mounted',{items:state.items.length,groups:state.groups.length,mealIntegration:mealModulePresent()});return remove},async deactivate(){await remove()},async unmount(){await remove()}}
}
