// @loom-file release=0.15.33 revision=3 policy=package-priority
(() => {
'use strict';
const CFG=window.LoomConfig||window.PegboardEngineConfig;
const qs=new URLSearchParams(location.search);
const project=(qs.get('project')||'').replace(/[^a-z0-9_-]/g,'');
const apiBase='../api';
if(!project){
  document.title='LOOM Pegboard';
  const status=document.getElementById('targetStatus');if(status)status.textContent='Select a project from LOOM Home to open Pegboard.';
  const badge=document.getElementById('runtimeBadge');if(badge){badge.className='runtime-badge offline';badge.textContent='No project selected';}
  const appLink=document.getElementById('appLink');if(appLink)appLink.href='../home/';
  return;
}
const fallback=`../projects/${project}/registry.fallback.json`;
const registryClient=new PegboardRegistryClient({project,apiBase,fallbackUrl:fallback});
const bus=new LoomEventBus(project,null);
const viewport=document.getElementById('viewport'),world=document.getElementById('world'),nodesEl=document.getElementById('nodes'),edgesEl=document.getElementById('edges'),detail=document.getElementById('detail'),eventLog=document.getElementById('eventLog');
const clientSelect=document.getElementById('clientSelect'),sessionSelect=document.getElementById('sessionSelect'),followLive=document.getElementById('followLive'),actionFilter=document.getElementById('actionFilter'),analytics=document.getElementById('analytics'),targetStatus=document.getElementById('targetStatus'),runtimeBadge=document.getElementById('runtimeBadge');
document.title=`${project.replace(/-/g,' ').replace(/\b\w/g,c=>c.toUpperCase())} · LOOM Pegboard`;
document.getElementById('appLink').href=`../projects/${project}/app/index.html?project=${project}`;

let registry=[],states=new Map(),stepStates=new Map(),nodeEls=new Map(),positions=new Map(),pan={x:Math.max(240,viewport.clientWidth*.18),y:Math.max(220,viewport.clientHeight*.48),scale:.82},drag=null,clients=[],selectedClient=null,selectedSession=null,sessionEvents=[],seenIds=new Set(),eventPollBusy=false,targetRefreshBusy=false,liveRefreshTimer=null;
const pulseTimers=new Map();
const builtins=[
 {action:{id:'engine.power',name:'LOOM Pegboard Power',description:'Permanent visualization root for the Pegboard viewer itself.',kind:'system',behavior:'stateful',parent:null,steps:[]},constant:true,fingerprint:'builtin'},
 {action:{id:'session.presence',name:'Session Presence',description:'Heartbeat freshness for the selected LOOM runtime. A stale heartbeat is resumable and never ends a session.',kind:'system',behavior:'stateful',parent:'engine.power',steps:[{id:'heartbeat',name:'Heartbeat freshness'},{id:'graceful-close',name:'Graceful unload'},{id:'stale-detector',name:'Stale heartbeat detector'}]},fingerprint:'builtin'},
 {action:{id:'core.load',name:'Load Core',description:'Project runtime boot and module discovery lifecycle.',kind:'system',behavior:'stateful',parent:'session.presence',steps:[{id:'discover-project',name:'Discover project'},{id:'discover-modules',name:'Discover modules'},{id:'validate-manifests',name:'Validate manifests'},{id:'ready',name:'Runtime ready'}]},fingerprint:'builtin'},
 {action:{id:'project.modules',name:'Project Modules',description:'Pegboard organizational root for project module cards. This is a visual grouping node, not a separate application capability.',kind:'system',behavior:'stateful',parent:'core.load',steps:[]},virtual:true,fingerprint:'builtin'}
];
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const moduleActions=()=>[...builtins,...registry];
function userActionDescriptors(){const out=[];for(const m of registry)for(const ua of m.user_actions||[])out.push({action:{...ua,kind:'user',behavior:ua.behavior||'transient',parent:m.action.id,steps:[]},folder:m.folder,moduleOwner:m,userAction:true});return out}
function allDescriptors(){return [...moduleActions(),...userActionDescriptors()]}
function actionDescriptor(id){return allDescriptors().find(m=>m.action.id===id)||null}
function stateFor(id){if(id==='project.modules')return states.get('core.load')||'available';return states.get(id)||'available'}
function setTransform(){world.style.transform=`translate(${pan.x}px,${pan.y}px) scale(${pan.scale})`}
function clearActionSteps(actionId){const d=actionDescriptor(actionId);for(const s of d?.action?.steps||[])stepStates.delete(`${actionId}::${s.id}`)}
function actionCount(id){return sessionEvents.filter(e=>e.type==='action.state'&&e.actionId===id&&e.state==='active').length}
function filterText(){return String(actionFilter?.value||'').trim().toLowerCase()}
function visibleRegistry(){const q=filterText();if(!q)return registry;return registry.filter(m=>{const hay=[m.action.id,m.action.name,m.action.category,...(m.action.tags||[]),...(m.user_actions||[]).flatMap(a=>[a.id,a.name,a.description])].join(' ').toLowerCase();return hay.includes(q)})}
function visibleUserActions(m){const q=filterText(),list=m.user_actions||[];if(!q)return list;const moduleHit=[m.action.id,m.action.name,m.action.category,...(m.action.tags||[])].join(' ').toLowerCase().includes(q);if(moduleHit)return list;return list.filter(a=>[a.id,a.name,a.description,...(a.tags||[])].join(' ').toLowerCase().includes(q))}
function layout(){
  positions.clear();
  positions.set('engine.power',{x:-430,y:0});positions.set('session.presence',{x:-155,y:0});positions.set('core.load',{x:120,y:0});positions.set('project.modules',{x:410,y:0});
  const mods=visibleRegistry(),cols=Math.max(1,Math.min(Number(CFG?.pegboard?.moduleColumns||3),mods.length||1)),rows=Math.ceil(mods.length/cols),x0=800,xGap=350,yGap=250;
  mods.forEach((m,i)=>{const col=i%cols,row=Math.floor(i/cols);positions.set(m.action.id,{x:x0+col*xGap,y:(row-(rows-1)/2)*yGap})});
}
function addEdge(from,to,cls='edge'){
  if(!positions.has(from)||!positions.has(to))return;const p1=positions.get(from),p2=positions.get(to),sx=p1.x+5000,sy=p1.y+5000,ex=p2.x+5000,ey=p2.y+5000,cx=(sx+ex)/2;const path=document.createElementNS('http://www.w3.org/2000/svg','path');path.setAttribute('d',`M ${sx} ${sy} C ${cx} ${sy}, ${cx} ${ey}, ${ex} ${ey}`);path.setAttribute('class',cls);edgesEl.appendChild(path)
}
function render(){
  layout();nodesEl.innerHTML='';edgesEl.innerHTML='';nodeEls.clear();
  const renderList=[...builtins,...visibleRegistry()];
  for(const m of renderList){
    const a=m.action,p=positions.get(a.id)||{x:0,y:0};const div=document.createElement('div');const st=stateFor(a.id);div.className=`node ${m.fingerprint==='builtin'?'system-spine':''} ${a.id==='engine.power'?'power constant':''} ${a.id==='core.load'?'core':''} ${a.id==='project.modules'?'group-root':''} ${st}`;div.style.left=p.x+'px';div.style.top=p.y+'px';div.dataset.id=a.id;
    const steps=(a.steps||[]).map(s=>`<span class="step-light ${stepStates.get(a.id+'::'+s.id)||''}" title="${esc(s.name)}"></span>`).join('');
    const order=m.order_display||m.module?.order||'';
    div.innerHTML=`<div class="top"><span class="bulb"></span><div><h3>${esc(a.name)}</h3><div class="id">${esc(a.id)}</div></div></div><div class="kind"><span>${esc(a.kind)} · ${esc(a.behavior)}</span>${order?`<span class="order-badge">${esc(order)}</span>`:''}</div>${steps?`<div class="steps">${steps}</div>`:''}`;
    if(!m.virtual&&m.fingerprint!=='builtin'){
      const box=document.createElement('div');box.className='module-actions';const title=document.createElement('div');title.className='module-actions-title';title.textContent='USER ACTIONS';box.appendChild(title);const wrap=document.createElement('div');wrap.className='ua-wrap';const uas=visibleUserActions(m);
      if(!uas.length){const none=document.createElement('div');none.className='no-actions';none.textContent=(m.user_actions||[]).length?'No matching user actions':'No declared user actions';wrap.appendChild(none)}
      for(const ua of uas){const pill=document.createElement('button');pill.type='button';const ust=stateFor(ua.id),count=actionCount(ua.id);pill.className=`ua-pill ${ust} ${count?'touched':''}`;pill.dataset.actionId=ua.id;pill.innerHTML=`<span class="ua-light"></span><span>${esc(ua.name)}</span>${count?`<span class="ua-count">×${count}</span>`:''}`;pill.onclick=e=>{e.stopPropagation();showActionDetail({action:{...ua,kind:'user',behavior:ua.behavior||'transient',parent:m.action.id,steps:[]},folder:m.folder,moduleOwner:m,userAction:true})};wrap.appendChild(pill)}
      box.appendChild(wrap);div.appendChild(box)
    }
    div.onclick=()=>showActionDetail(m);nodesEl.appendChild(div);nodeEls.set(a.id,div)
  }
  addEdge('engine.power','session.presence');addEdge('session.presence','core.load');addEdge('core.load','project.modules');for(const m of visibleRegistry())addEdge('project.modules',m.action.id,'edge module-edge')
}
function showActionDetail(m){
  const a=m.action,relevant=sessionEvents.filter(e=>e.actionId===a.id).slice(-30).reverse();const isUser=a.kind==='user';detail.innerHTML=`<div class="detail-title">${esc(a.name)}</div><div class="detail-desc">${esc(a.description||'')}</div><div class="detail-grid"><b>Action ID</b><span>${esc(a.id)}</span><b>State</b><span>${esc(stateFor(a.id))}</span><b>Kind</b><span>${esc(a.kind)}</span><b>Behavior</b><span>${esc(a.behavior)}</span><b>${isUser?'Owning module':'Parent'}</b><span>${esc(a.parent||'—')}</span><b>Module folder</b><span>${esc(m.folder||m.moduleOwner?.folder||'engine builtin')}</span><b>Executions</b><span>${actionCount(a.id)}</span><b>Events in session</b><span>${relevant.length}</span></div>${relevant.length?`<div class="detail-json">${esc(relevant.map(e=>`${stamp(e)}  ${e.type}  ${e.state||''} ${e.domainEvent||''} ${e.reason||''}`).join('\n'))}</div>`:''}`
}
async function refreshRegistry(){const data=await registryClient.load();registry=data.modules||[];const valid=new Set([...registry.map(m=>m.action.id),...registry.flatMap(m=>(m.user_actions||[]).map(a=>a.id))]);for(const id of [...states.keys()])if(!valid.has(id)&&!id.startsWith('core.')&&!id.startsWith('engine.')&&!id.startsWith('session.')&&id!=='project.modules')states.delete(id);render()}
function stamp(e){return String(e.serverTimestamp||e.clientTimestamp||'').replace('T',' ').replace('Z','')}
function resetSessionState(){for(const t of pulseTimers.values())clearTimeout(t);pulseTimers.clear();states=new Map([['engine.power','active']]);stepStates=new Map();sessionEvents=[];seenIds=new Set();eventLog.innerHTML='';document.getElementById('eventCount').textContent='0 events'}
function setInactive(id){if(!id||id==='engine.power')return;states.set(id,'available');clearActionSteps(id)}
function applySessionShutdown(ids=[]){const target=ids.length?ids:[...states.keys()].filter(id=>id!=='engine.power');for(const id of target)setInactive(id);setInactive('core.load');setInactive('session.presence')}
function applyPresenceSnapshot(s){
  if(!s)return;
  const active=new Set(s.activeActionIds||[]);active.add('session.presence');active.add('core.load');
  if(s.active){
    for(const m of moduleActions()){const id=m.action.id;if(id==='engine.power'||id==='project.modules')continue;if(m.action.behavior==='stateful'||m.action.behavior==='pending'){states.set(id,active.has(id)?'active':'available');if(!active.has(id))clearActionSteps(id)}}
    for(const d of userActionDescriptors()){if(d.action.behavior==='stateful'||d.action.behavior==='pending')states.set(d.action.id,active.has(d.action.id)?'active':'available')}
    states.set('session.presence','active');states.set('core.load','active');stepStates.set('session.presence::heartbeat','active');stepStates.delete('session.presence::stale-detector');
  }else if(s.status==='stale'){
    for(const m of moduleActions()){const id=m.action.id;if(id==='engine.power'||id==='project.modules')continue;if(m.action.behavior==='stateful'||m.action.behavior==='pending'){states.set(id,active.has(id)?'stale':'available');if(!active.has(id))clearActionSteps(id)}}
    for(const d of userActionDescriptors()){if(d.action.behavior==='stateful'||d.action.behavior==='pending')states.set(d.action.id,active.has(d.action.id)?'stale':'available')}
    states.set('session.presence','stale');states.set('core.load','stale');stepStates.set('session.presence::heartbeat','stale');stepStates.set('session.presence::stale-detector','completed');
  }else{
    applySessionShutdown(s.activeActionIds||[]);stepStates.delete('session.presence::heartbeat');stepStates.delete('session.presence::stale-detector');if(s.status==='closed')stepStates.set('session.presence::graceful-close','completed');if(s.status==='expired')stepStates.set('session.presence::stale-detector','failed');
  }
  render()
}
function pulseUser(id,state='pulse'){
  clearTimeout(pulseTimers.get(id));states.set(id,state);render();pulseTimers.set(id,setTimeout(()=>{if(states.get(id)===state){states.set(id,'available');render()}},Number(CFG?.pegboard?.userActionPulseMs||900)))
}
function applyEvent(e,{history=false}={}){
  if(!e)return;if(e.id&&seenIds.has(e.id))return;if(e.id)seenIds.add(e.id);sessionEvents.push(e);const d=e.actionId?actionDescriptor(e.actionId):null,isUser=e.kind==='user'||d?.action?.kind==='user';
  if(e.type==='action.state'){
    if(isUser){if(history){states.set(e.actionId,e.state==='active'&&(e.behavior==='pending'||e.behavior==='stateful')?'active':'available')}else if(e.state==='active')states.set(e.actionId,'active');else if(e.state==='completed')pulseUser(e.actionId,'pulse');else if(e.state==='failed')pulseUser(e.actionId,'failed');else if(e.state==='inactive')states.set(e.actionId,'available')}
    else{const mapped=e.state==='inactive'?'available':e.state;if(e.actionId)states.set(e.actionId,mapped);if(e.state==='inactive'&&e.actionId)clearActionSteps(e.actionId);if(!history&&e.state==='completed'){const n=nodeEls.get(e.actionId);if(n){n.classList.add('pulse');setTimeout(()=>n.classList.remove('pulse'),800)}}}
  }
  if(e.type==='action.step'&&e.actionId&&e.stepId)stepStates.set(`${e.actionId}::${e.stepId}`,e.state);
  if(e.type==='session.stale'){const held=e.heldActionIds||[];for(const id of held)if(id!=='engine.power')states.set(id,'stale');states.set('session.presence','stale');states.set('core.load','stale');stepStates.set('session.presence::heartbeat','stale');stepStates.set('session.presence::stale-detector','completed')}
  if(e.type==='session.expired'){applySessionShutdown(e.expiredActionIds||[]);stepStates.set('session.presence::stale-detector','failed')} // legacy v0.8 history only
  if(e.type==='session.end'){setInactive('session.presence');setInactive('core.load');stepStates.set('session.presence::graceful-close','completed')}
  if(e.type==='session.resumed'){states.set('session.presence','active');states.set('core.load','active');for(const id of e.resumedActionIds||[])if(id!=='engine.power')states.set(id,'active');stepStates.set('session.presence::heartbeat','active');stepStates.delete('session.presence::stale-detector')}
  addLog(e);render()
}
function addLog(e){const row=document.createElement('div');row.className=`log-row ${e.kind==='user'?'user':''}`;row.innerHTML=`<span class="t">${esc(stamp(e))}</span> <span class="type">${esc(e.type||'event')}</span> <span class="a">${esc(e.actionId||'')}</span> ${esc(e.state||'')} ${esc(e.domainEvent||e.reason||'')}`;row.onclick=()=>{detail.innerHTML=`<div class="detail-title">Event</div><div class="detail-desc">${esc(e.type||'event')} · ${esc(stamp(e))}</div><div class="detail-json">${esc(JSON.stringify(e,null,2))}</div>`};eventLog.appendChild(row);document.getElementById('eventCount').textContent=`${sessionEvents.length} events`}
function lastSeenMs(s){const n=Date.parse(s?.lastSeen||'');return Number.isFinite(n)?n:0}
function newestLive(list=[]){return [...list].filter(s=>s.active).sort((a,b)=>lastSeenMs(b)-lastSeenMs(a))[0]||null}
async function refreshTargets(){
  if(targetRefreshBusy)return;targetRefreshBusy=true;try{const r=await fetch(`${apiBase}/sessions.php?project=${encodeURIComponent(project)}&_=${Date.now()}`,{cache:'no-store'});if(!r.ok)throw 0;const data=await r.json();clients=data.clients||[];const stored=localStorage.getItem(`loom:${project}:pegboard-client`)||'';const wanted=clientSelect.value||selectedClient?.clientId||stored||clients.find(c=>c.active)?.clientId||clients[0]?.clientId||'';clientSelect.innerHTML=clients.length?clients.map(c=>`<option value="${esc(c.clientId)}">${c.active?'● ':c.stale?'◐ ':''}${esc(c.userLabel||c.clientId)} · ${c.sessionCount} session${c.sessionCount===1?'':'s'}</option>`).join(''):'<option value="">No sessions yet — open the app</option>';if(clients.some(c=>c.clientId===wanted))clientSelect.value=wanted;else if(clients[0])clientSelect.value=clients[0].clientId;selectedClient=clients.find(c=>c.clientId===clientSelect.value)||clients[0]||null;if(selectedClient){localStorage.setItem(`loom:${project}:pegboard-client`,selectedClient.clientId);populateSessions(selectedClient)}else{selectedSession=null;sessionSelect.innerHTML='<option value="">—</option>';paintAnalytics()}}catch(err){targetStatus.textContent='Session API unavailable'}finally{targetRefreshBusy=false}}
function statusLabel(s){if(s.active)return'● LIVE';if(s.status==='stale')return'◐ STALE · RETAINED';if(s.status==='expired')return'○ LEGACY EXPIRED';if(s.status==='closed')return'○ CLOSED';return'○ HISTORY'}
function populateSessions(client){
  const rank=s=>s.active?2:(s.status==='stale'?1:0);const list=[...(client.sessions||[])].sort((a,b)=>(rank(b)-rank(a))||lastSeenMs(b)-lastSeenMs(a));const old=selectedSession?.sessionId||sessionSelect.value||localStorage.getItem(`loom:${project}:pegboard-session`)||'';sessionSelect.innerHTML=list.map((s,i)=>`<option value="${esc(s.sessionId)}">${statusLabel(s)} · ${i===0?'LATEST · ':''}${esc(formatWhen(s.lastSeen))} · ${s.eventCount} events</option>`).join('')||'<option value="">No sessions</option>';
  let choose='';if(followLive.checked){const live=newestLive(list);choose=live?.sessionId||(list.some(s=>s.sessionId===old)?old:list[0]?.sessionId||'')}else choose=list.some(s=>s.sessionId===old)?old:(list[0]?.sessionId||'');sessionSelect.value=choose;const next=list.find(s=>s.sessionId===choose)||null;
  if(!selectedSession||selectedSession.sessionId!==next?.sessionId){selectedSession=next;if(next){localStorage.setItem(`loom:${project}:pegboard-session`,next.sessionId);loadSelectedSession()}else paintAnalytics()}else{selectedSession=next;applyPresenceSnapshot(next);paintAnalytics()}
}
function formatWhen(iso){if(!iso)return'unknown';try{return new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',second:'2-digit'}).format(new Date(iso))}catch{return iso}}
async function loadSelectedSession(){if(!selectedSession)return;resetSessionState();await pollEvents(true);applyPresenceSnapshot(selectedSession);paintAnalytics()}
async function pollEvents(full=false){if(!selectedSession||eventPollBusy)return;eventPollBusy=true;try{const r=await fetch(`${apiBase}/session-events.php?project=${encodeURIComponent(project)}&session_id=${encodeURIComponent(selectedSession.sessionId)}&limit=5000&_=${Date.now()}`,{cache:'no-store'});if(r.ok){const d=await r.json();if(full)resetSessionState();for(const e of d.events||[])applyEvent(e,{history:!selectedSession.active});if(selectedSession)applyPresenceSnapshot(selectedSession)}}finally{eventPollBusy=false}}
function paintAnalytics(){const c=selectedClient,s=selectedSession;if(!c||!s){targetStatus.textContent='No session selected';analytics.innerHTML='';runtimeBadge.className='runtime-badge offline';runtimeBadge.textContent='No LOOM session selected';return}const status=s.active?'LIVE NOW':String(s.status||'historical').toUpperCase();targetStatus.textContent=`${c.userLabel||c.clientId} · ${status}${followLive.checked?' · AUTO-FOLLOW ON':''}`;const stale=s.status==='stale';runtimeBadge.className=`runtime-badge ${s.active?'online':stale?'stale':'history'}`;runtimeBadge.textContent=s.active?'LOOM session live':stale?'LOOM session waiting · reconnectable':s.status==='expired'?'Legacy heartbeat-expired session':'Saved LOOM session';const m=s.meta||{},freshness=s.active?`${Math.max(0,Math.ceil((s.leaseRemainingMs||0)/1000))}s`:stale?`stale ${Math.max(0,Math.ceil((s.staleForMs||0)/1000))}s`:'—',endReason=s.closedReason||(s.status==='expired'?s.expiredReason:null)||'—',presence=stale?'stale / resumable':s.active?'live':'inactive';analytics.innerHTML=`<div class="stat"><b>${s.eventCount}</b><span>events</span></div><div class="stat"><b>${s.userActionCount||0}</b><span>user actions</span></div><div class="stat"><b>${s.uniqueActionCount}</b><span>actions touched</span></div><div class="stat"><b>${s.failureCount}</b><span>failures</span></div><div class="stat"><b>${(s.activeActionIds||[]).length}</b><span>held snapshot</span></div><div class="stat"><b>${esc(freshness)}</b><span>heartbeat freshness</span></div><div class="info-row"><strong>${esc(c.userLabel||'Anonymous')}</strong><br>Client: ${esc(c.clientId)}<br>Session: ${esc(s.sessionId)}<br>LOOM run: ${esc(s.runtimeId||'—')}<br>Presence: ${esc(presence)}<br>First: ${esc(formatWhen(s.firstSeen))}<br>Last heartbeat: ${esc(formatWhen(s.lastSeen))}<br>Session end: ${esc(endReason)}<br>Timezone: ${esc(m.timezone||'—')} · Language: ${esc(m.language||'—')}<br>Viewport: ${esc(m.viewport?`${m.viewport.width}×${m.viewport.height} @ ${m.viewport.devicePixelRatio||1}x`:'—')}</div>`}
function fitGraph(){
  layout();
  const pts=[...positions.values()];
  if(!pts.length)return;
  const minX=Math.min(...pts.map(p=>p.x))-180,maxX=Math.max(...pts.map(p=>p.x))+180;
  const minY=Math.min(...pts.map(p=>p.y))-150,maxY=Math.max(...pts.map(p=>p.y))+150;
  const w=Math.max(1,maxX-minX),h=Math.max(1,maxY-minY);
  const vw=Math.max(520,viewport.clientWidth||innerWidth);
  const vh=Math.max(360,viewport.clientHeight||innerHeight);
  const availW=Math.max(440,vw-80),availH=Math.max(300,vh-90);
  const scale=Math.max(.28,Math.min(1,availW/w,availH/h));
  pan.scale=scale;
  pan.x=vw/2-((minX+maxX)/2)*scale;
  pan.y=vh/2-((minY+maxY)/2)*scale;
  setTransform();
}
clientSelect.onchange=()=>{selectedClient=clients.find(c=>c.clientId===clientSelect.value)||null;if(selectedClient){localStorage.setItem(`loom:${project}:pegboard-client`,selectedClient.clientId);selectedSession=null;populateSessions(selectedClient)}};
sessionSelect.onchange=()=>{const next=selectedClient?.sessions?.find(s=>s.sessionId===sessionSelect.value)||null;const live=newestLive(selectedClient?.sessions||[]);if(followLive.checked&&live&&next?.sessionId!==live.sessionId){followLive.checked=false;localStorage.setItem(`loom:${project}:pegboard-follow-live`,'0')}selectedSession=next;if(selectedSession){localStorage.setItem(`loom:${project}:pegboard-session`,selectedSession.sessionId);loadSelectedSession()}};
followLive.onchange=()=>{localStorage.setItem(`loom:${project}:pegboard-follow-live`,followLive.checked?'1':'0');if(followLive.checked)refreshTargets();paintAnalytics()};
actionFilter.oninput=()=>render();
bus.on(e=>{if(selectedSession&&e.sessionId===selectedSession.sessionId)applyEvent(e);if(followLive.checked&&(!selectedClient||e.clientId===selectedClient.clientId)&&e.sessionId&&e.sessionId!==selectedSession?.sessionId){clearTimeout(liveRefreshTimer);liveRefreshTimer=setTimeout(()=>refreshTargets(),Number(CFG?.pegboard?.liveSwitchDebounceMs||100))}});
viewport.addEventListener('pointerdown',e=>{if(e.target.closest('.node'))return;e.preventDefault();drag={x:e.clientX-pan.x,y:e.clientY-pan.y};viewport.classList.add('dragging');viewport.setPointerCapture(e.pointerId)});
viewport.addEventListener('pointermove',e=>{if(!drag)return;e.preventDefault();pan.x=e.clientX-drag.x;pan.y=e.clientY-drag.y;setTransform()});
viewport.addEventListener('pointerup',e=>{drag=null;viewport.classList.remove('dragging');try{viewport.releasePointerCapture(e.pointerId)}catch{}});
viewport.addEventListener('pointercancel',()=>{drag=null;viewport.classList.remove('dragging')});
viewport.addEventListener('wheel',e=>{e.preventDefault();const old=pan.scale,next=Math.max(.25,Math.min(2.2,old*Math.exp(-e.deltaY*.001))),rect=viewport.getBoundingClientRect(),mx=e.clientX-rect.left,my=e.clientY-rect.top;pan.x=mx-(mx-pan.x)*(next/old);pan.y=my-(my-pan.y)*(next/old);pan.scale=next;setTransform()},{passive:false});
document.getElementById('fitBtn').onclick=fitGraph;document.getElementById('refreshBtn').onclick=()=>refreshRegistry();document.getElementById('clearLog').onclick=()=>{eventLog.innerHTML=''};
const themeSelect=document.getElementById('themeSelect');
const availableThemes=new Set([...themeSelect.options].map(o=>o.value));
themeSelect.onchange=e=>{document.body.classList.remove(...[...availableThemes].map(v=>`theme-${v}`));document.body.classList.add(`theme-${e.target.value}`);localStorage.setItem('loom:pegboard-theme',e.target.value)};
let savedTheme=localStorage.getItem('loom:pegboard-theme')||themeSelect.value||'loom-dark';if(!availableThemes.has(savedTheme))savedTheme='loom-dark';themeSelect.value=savedTheme;document.body.classList.remove(...[...availableThemes].map(v=>`theme-${v}`));document.body.classList.add(`theme-${savedTheme}`);localStorage.setItem('loom:pegboard-theme',savedTheme);
const savedFollow=localStorage.getItem(`loom:${project}:pegboard-follow-live`);followLive.checked=savedFollow===null?!!(CFG?.pegboard?.autoFollowLiveDefault??true):savedFollow==='1';
setTransform();
refreshRegistry().then(()=>fitGraph());
refreshTargets();
if('ResizeObserver' in window){
  let resizeTimer=null;
  new ResizeObserver(()=>{clearTimeout(resizeTimer);resizeTimer=setTimeout(fitGraph,90)}).observe(viewport);
}
setInterval(()=>refreshRegistry().catch(()=>{}),3000);
setInterval(()=>refreshTargets().catch(()=>{}),CFG.sessionRefreshMs);
setInterval(()=>pollEvents(false),CFG.telemetryRefreshMs);
})();
