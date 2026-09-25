<?php
// @loom-file release=0.15.51 revision=35 policy=package-priority
require __DIR__.'/../../api/_common.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$loomPageMeta=loom_generic_social_meta(loom_absolute_web_url(rtrim(web_base_path(),'/').'/admin/activity/'),'LOOM Activity Explorer','LOOM administrative activity explorer.');$loomPageMeta['robots']='noindex,nofollow';$loomPageSocial=loom_social_meta_html($loomPageMeta,false);
loom_native_admin_page_guard('Activity Explorer','../../');
?><!doctype html>
<html lang="en">
<head><?php echo $loomPageSocial; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">

<link rel="icon" type="image/png" href="../../assets/loom-logo.png?v=0.15.21">
<style>
:root{--ink:#132019;--muted:#6d7b72;--green:#168346;--line:#dce9df;--soft:#f5faf6;--red:#9f2929}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,sans-serif;color:var(--ink);background:radial-gradient(circle at 20% -10%,#dff6e6,#f6faf6 45%,#edf4ee)}
button,input,select{font:inherit}.wrap{width:min(1320px,calc(100% - 28px));margin:26px auto 60px}.hero,.filters,.results{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 18px 60px #183c2410}
.hero{padding:22px}.head{display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}.eyebrow{font-size:9px;font-weight:950;letter-spacing:.14em;color:#57705f;text-transform:uppercase}.hero h1{margin:4px 0 6px;font-size:38px;letter-spacing:-.045em}.hero p{margin:0;color:var(--muted);font-size:12px;line-height:1.55}.nav{display:flex;gap:7px;flex-wrap:wrap}.nav a,.btn{border:1px solid #d2e2d5;border-radius:10px;background:#f8fcf9;color:#245b36;padding:9px 11px;text-decoration:none;font-weight:850;font-size:10px;cursor:pointer}.btn.primary{background:#173f27;color:white;border-color:#173f27}.filters{padding:18px;margin-top:12px}.filter-grid{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:10px}.field label{display:block;font-size:9px;font-weight:900;color:#58705f;margin-bottom:5px}.field input,.field select{width:100%;padding:10px 11px;border:1px solid #d4e3d7;border-radius:10px;background:white;min-width:0}.actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:12px}.summary{display:grid;grid-template-columns:repeat(6,minmax(100px,1fr));gap:9px;margin:12px 0}.stat{padding:12px;border:1px solid #dce8df;border-radius:14px;background:#fff}.stat b{display:block;font-size:20px}.stat span{font-size:8px;text-transform:uppercase;letter-spacing:.08em;color:#75837a;font-weight:900}.results{overflow:hidden}.result-head{padding:15px 17px;border-bottom:1px solid #e5eee7;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.rows{display:grid}.row{display:grid;grid-template-columns:155px 110px minmax(230px,1.35fr) minmax(170px,.75fr) minmax(150px,.7fr);gap:12px;padding:12px 16px;border-bottom:1px solid #edf2ee;align-items:start}.row:last-child{border-bottom:0}.time{font-size:9px;color:#64766b}.badge{display:inline-block;padding:5px 7px;border-radius:999px;background:#eaf6ed;color:#17633a;font-size:8px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}.badge.interactions{background:#eef0ff;color:#414c8a}.badge.framed{background:#f0eaff;color:#65418a}.badge.errors{background:#ffe9e9;color:#922c2c}.main strong{display:block;font-size:11px;line-height:1.35}.main code,.meta code{font:800 8px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;color:#42604b}.meta{font-size:9px;color:#6d7d73;line-height:1.5;min-width:0}.detail summary{cursor:pointer;font-size:9px;font-weight:850;color:#3b6046}.detail pre{white-space:pre-wrap;word-break:break-word;font:8px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;background:#f7faf7;border:1px solid #e1e9e3;border-radius:10px;padding:8px;max-height:220px;overflow:auto}.empty{padding:28px;text-align:center;color:#75837a;font-size:11px}.msg{font-size:9px;color:#607268;min-height:14px}
@media(max-width:1000px){.filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.summary{grid-template-columns:repeat(3,minmax(0,1fr))}.row{grid-template-columns:130px 90px minmax(0,1fr);}.row .who,.row .session{grid-column:3}}
@media(max-width:640px){.wrap{width:min(100% - 14px,1320px);margin:12px auto 36px}.hero,.filters{padding:15px;border-radius:18px}.hero h1{font-size:30px}.filter-grid{grid-template-columns:1fr}.summary{grid-template-columns:repeat(2,minmax(0,1fr))}.row{grid-template-columns:1fr;gap:6px;padding:13px}.row .who,.row .session{grid-column:auto}.result-head{padding:13px}.actions .btn{flex:1}.nav a{flex:1;text-align:center}}
</style>
</head>
<body>
<div id="loomShellHeader"></div>
<div class="wrap">
  <section class="hero">
    <div class="head">
      <div><div class="eyebrow">LOOM ADMIN · HISTORICAL OBSERVABILITY</div><h1>Activity Explorer</h1><p>Search past user actions, clicks/taps, field interactions, sessions, module events, errors, and framed HTML actions. Pegboard remains the live-debug surface; this page is for historical investigation.</p></div>
      <div class="nav"><a href="../">Admin</a><a id="pegboardLink" href="../../pegboard/" target="_blank">Pegboard Live</a><a href="../../home/">LOOM Home</a></div>
    </div>
  </section>

  <section class="filters">
    <div class="filter-grid">
      <div class="field"><label>Project</label><select id="project"></select></div>
      <div class="field"><label>User / guest client</label><select id="subject"><option value="">All users</option></select></div>
      <div class="field"><label>Category</label><select id="category"><option value="all">Everything</option><option value="actions">User actions</option><option value="interactions">Clicks / inputs / scroll</option><option value="framed">Framed HTML</option><option value="sessions">Sessions</option><option value="modules">Module/runtime</option><option value="errors">Errors</option></select></div>
      <div class="field"><label>Session</label><select id="session"><option value="">All sessions</option></select></div>
      <div class="field"><label>From</label><input id="from" type="date"></div>
      <div class="field"><label>Through</label><input id="to" type="date"></div>
      <div class="field"><label>Search</label><input id="q" type="search" placeholder="action ID, button, target, reason…"></div>
      <div class="field"><label>Maximum rows</label><select id="limit"><option>250</option><option selected>500</option><option>1000</option><option>2000</option></select></div>
    </div>
    <div class="actions"><button id="load" class="btn primary">Load Activity</button><button id="clear" class="btn">Clear Filters</button><button id="export" class="btn">Export Current JSON</button><span id="msg" class="msg"></span></div>
  </section>

  <div id="summary" class="summary"></div>
  <section class="results">
    <div class="result-head"><div><b id="resultTitle">Activity</b><div class="msg" id="resultNote"></div></div><span class="badge" id="matched">0 matched</span></div>
    <div id="rows" class="rows"><div class="empty">Choose filters and load activity.</div></div>
  </section>
</div>
<div id="loomShellFooter"></div>
<script src="../../engine/deployment-guard.js?v=0.15.51"></script><script src="../../engine/loom-brand.js?v=0.15.51"></script>
<script src="../../engine/identity.js?v=0.15.51"></script>
<script src="../../engine/loom-global-profile.js?v=0.15.51"></script>
<script src="../../engine/loom-toast.js?v=0.15.51"></script><script src="../../engine/share-referrals.js?v=0.15.51"></script><script src="../../engine/loom-shell.js?v=0.15.51"></script>
<script>
const $=s=>document.querySelector(s),esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const identity=LoomIdentity.get('loom-admin');let latest=null,subjects=[];
const fmt=v=>{if(!v)return'—';const d=new Date(v);return Number.isNaN(d.getTime())?String(v):d.toLocaleString()};
async function json(url,options={}){const r=await fetch(url,{cache:'no-store',...options});const j=await r.json();if(!r.ok||j.ok===false)throw new Error(j.error||j.message||`${r.status}`);return j}
async function projects(){const j=await json(`../../api/projects.php?clientId=${encodeURIComponent(identity.clientId)}&_=${Date.now()}`);$('#project').innerHTML=(j.projects||[]).map(p=>`<option value="${esc(p.slug)}">${esc(p.name||p.slug)}</option>`).join('');const asked=new URLSearchParams(location.search).get('project');if(asked&&[...$('#project').options].some(o=>o.value===asked))$('#project').value=asked}
async function loadSubjects(){const project=$('#project').value;const j=await json('../../api/admin-users.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'list',project,clientId:identity.clientId})});subjects=j.users||[];const keep=$('#subject').value;$('#subject').innerHTML='<option value="">All users</option>'+subjects.map(u=>`<option value="${esc(u.subjectType+':'+u.subjectId)}">${u.registered?'👤':'🟢'} ${esc(u.username||u.subjectId)}${u.email?' · '+esc(u.email):''}</option>`).join('');if(keep&&[...$('#subject').options].some(o=>o.value===keep))$('#subject').value=keep;$('#pegboardLink').href=`../../pegboard/?project=${encodeURIComponent(project)}`}
function params(){const p=new URLSearchParams({project:$('#project').value,category:$('#category').value,limit:$('#limit').value});const sub=$('#subject').value;if(sub){const i=sub.indexOf(':');p.set('subjectType',sub.slice(0,i));p.set('subjectId',sub.slice(i+1))}for(const id of ['session','from','to','q'])if($('#'+id).value)p.set(id==='session'?'sessionId':id,$('#'+id).value);return p}
function summary(data){const c=data.counts||{};const defs=[['Matched',data.matched||0],['User actions',c.actions||0],['Interactions',c.interactions||0],['Framed HTML',c.framed||0],['Sessions',c.sessions||0],['Errors',c.errors||0]];$('#summary').innerHTML=defs.map(([n,v])=>`<div class="stat"><b>${esc(v)}</b><span>${esc(n)}</span></div>`).join('')}
function detailText(d){try{return JSON.stringify(d||{},null,2)}catch{return String(d||'')}}
function render(data){latest=data;summary(data);$('#matched').textContent=`${data.matched||0} matched`;$('#resultNote').textContent=`Showing ${data.returned||0} newest matching records · ${data.note||''}`;const old=$('#session').value;$('#session').innerHTML='<option value="">All sessions</option>'+(data.sessions||[]).map(s=>`<option value="${esc(s)}">${esc(s)}</option>`).join('');if(old&&[...$('#session').options].some(o=>o.value===old))$('#session').value=old;const rows=data.rows||[];if(!rows.length){$('#rows').innerHTML='<div class="empty">No matching historical activity.</div>';return}$('#rows').innerHTML=rows.map(r=>`<article class="row"><div class="time">${esc(fmt(r.timestamp))}</div><div><span class="badge ${esc(r.category||'')}">${esc(r.category||r.source||'event')}</span></div><div class="main"><strong>${esc(r.summary||r.type||'Activity')}</strong>${r.actionId?`<code>${esc(r.actionId)}</code>`:''}<details class="detail"><summary>Details</summary><pre>${esc(detailText(r.detail))}</pre></details></div><div class="meta who">${esc(r.userLabel||r.userId||r.clientId||'Unknown user')}<br><code>${esc(r.clientId||'')}</code></div><div class="meta session">${r.sessionId?'Session<br><code>'+esc(r.sessionId)+'</code>':'—'}</div></article>`).join('')}
async function load(){try{$('#msg').textContent='Loading…';const data=await json(`../../api/activity.php?${params().toString()}&_=${Date.now()}`);render(data);$('#msg').textContent=''}catch(e){$('#msg').textContent=e.message;$('#rows').innerHTML=`<div class="empty">${esc(e.message)}</div>`}}
$('#load').onclick=load;$('#project').onchange=async()=>{await loadSubjects();$('#session').innerHTML='<option value="">All sessions</option>';await load()};$('#subject').onchange=load;$('#category').onchange=load;$('#session').onchange=load;$('#q').addEventListener('keydown',e=>{if(e.key==='Enter')load()});
$('#clear').onclick=()=>{for(const id of ['subject','session','from','to','q'])$('#'+id).value='';$('#category').value='all';$('#limit').value='500';load()};
$('#export').onclick=()=>{if(!latest)return;const blob=new Blob([JSON.stringify(latest,null,2)],{type:'application/json'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=`loom-activity-${$('#project').value}-${new Date().toISOString().slice(0,10)}.json`;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000)};
(async()=>{try{await projects();await loadSubjects();if(window.LoomShell)await LoomShell.mount({apiBase:'../../api',identity,pageTitle:'Activity Explorer',links:[{label:'Admin',href:'../'},{label:'LOOM Home',href:'../../home/'}]});await load()}catch(e){document.body.innerHTML=`<div style="padding:30px;font-family:system-ui"><h1>Activity Explorer failed</h1><p>${esc(e.message)}</p><a href="../">Return to Admin</a></div>`}})();
</script>
</body>
</html>
