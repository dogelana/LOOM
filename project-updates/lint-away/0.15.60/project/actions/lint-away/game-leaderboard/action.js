// @loom-file release=0.15.60 revision=1 policy=package-priority
export async function createModule(ctx){
  let root=null,timer=null,onMessage=null,reporting=false,pending=null,alive=false;
  const boardId=String(ctx.config.boardId||'lint-away-game');
  const frameId=String(ctx.config.frameId||'hf_6ba08b1de94d64');
  const maxEntries=Math.max(3,Math.min(10,Number(ctx.config.maxEntries||10)));
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const trophy=kind=>`<span class="lal-trophy ${kind}" aria-label="${kind} trophy"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8v2h4v4c0 2.76-2.24 5-5 5h-.18A6.02 6.02 0 0 1 13 16.66V19h4v2H7v-2h4v-2.34A6.02 6.02 0 0 1 9.18 14H9c-2.76 0-5-2.24-5-5V5h4V3Zm8 4v2c0 .95-.22 1.85-.62 2.65A3 3 0 0 0 18 9V7h-2ZM6 7v2a3 3 0 0 0 2.62 2.98A5.96 5.96 0 0 1 8 9V7H6Z"/></svg></span>`;
  const rankHtml=n=>n===1?trophy('gold'):n===2?trophy('silver'):n===3?trophy('bronze'):`<span>${n}</span>`;
  const money=n=>{n=Math.max(0,Number(n||0));try{return new Intl.NumberFormat(undefined,{style:'currency',currency:'USD',maximumFractionDigits:n>=1000?0:2}).format(n)}catch{return '$'+Math.round(n).toLocaleString()}};
  const duration=sec=>{sec=Math.max(0,Math.round(Number(sec||0)));const h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;if(h)return `${h}h ${m}m`;if(m)return `${m}m ${s}s`;return `${s}s`};
  function frameEl(){return document.querySelector(`[data-html-frame-id="${CSS.escape(frameId)}"] iframe`)}
  function renderRows(rows=[]){if(!root)return;const table=root.querySelector('[data-board]');if(!table)return;const list=rows.slice(0,maxEntries);if(!list.length){table.innerHTML='<div class="lal-empty">No ranked players yet. Start cleaning a vent and the board will populate automatically.</div>';return}table.innerHTML=`<div class="lal-row lal-labels"><div class="lal-cell">Rank</div><div class="lal-cell">Player</div><div class="lal-cell">Lifetime earned</div><div class="lal-cell lal-time">Playtime</div></div>${list.map(r=>`<div class="lal-row ${r.isCurrent?'is-me':''}"><div class="lal-cell lal-rank">${rankHtml(Number(r.rank||0))}</div><div class="lal-cell lal-player">${esc(r.name||'Player')}${r.isCurrent?'<span class="lal-you">You</span>':''}</div><div class="lal-cell lal-money">${esc(money(r.earned))}</div><div class="lal-cell lal-time">${esc(duration(r.playSeconds))}</div></div>`).join('')}`}
  function status(text,error=false){if(!root)return;const el=root.querySelector('[data-status]');if(!el)return;el.textContent=text;el.classList.toggle('lal-error',!!error)}
  async function refresh(){if(!alive)return;try{const q=new URLSearchParams({clientId:ctx.identity.clientId,project:ctx.project,boardId,limit:String(maxEntries),_:String(Date.now())});const r=await ctx.fetchApi(`project-leaderboard.php?${q}`);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Leaderboard unavailable');renderRows(j.rows||[]);status('Live')}catch(e){status('Sync unavailable',true)}}
  async function drain(){if(reporting||!pending||!alive)return;reporting=true;while(pending&&alive){const payload=pending;pending=null;try{const r=await ctx.fetchApi('project-leaderboard.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({clientId:ctx.identity.clientId,project:ctx.project,boardId,limit:maxEntries,sessionId:ctx.identity.sessionId,...payload})});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Leaderboard report rejected');renderRows(j.rows||[]);status('Live')}catch(e){status('Sync unavailable',true)}}reporting=false}
  function queueReport(msg){const earned=Number(msg.earned);if(!Number.isFinite(earned)||earned<0)return;pending={runId:String(msg.runId||'').slice(0,96),earned,active:!!msg.active,reason:String(msg.reason||'game').slice(0,64)};if(!pending.runId)return;drain()}
  function requestSnapshot(){const f=frameEl();try{f?.contentWindow?.postMessage({__lintAwayTelemetryRequest:'v1',frameId},'*')}catch{}}
  function cleanup(){alive=false;if(timer)clearInterval(timer);timer=null;if(onMessage)window.removeEventListener('message',onMessage);onMessage=null;pending=null;root?.remove();root=null}
  return{
    async mount(){},
    async activate(){alive=true;root=document.createElement('section');root.className='lint-away-leaderboard';root.innerHTML=`<div class="lal-head"><div><div class="lal-kicker">Lint Away · global project standings</div><h2 class="lal-title">${esc(ctx.config.title||'Lint Away Game Leaderboard')}</h2></div><div class="lal-live" data-status>Connecting…</div></div><div class="lal-table" data-board><div class="lal-empty">Loading leaderboard…</div></div><div class="lal-note">Ranked by lifetime gross cash earned in the Lint Away game. Playtime counts only while gameplay is actively running.</div>`;ctx.mount(root);
      onMessage=event=>{const f=frameEl();if(!f||event.source!==f.contentWindow)return;const msg=event.data;if(!msg||typeof msg!=='object'||msg.__lintAwayGameStats!=='v1'||String(msg.frameId||'')!==frameId)return;queueReport(msg)};window.addEventListener('message',onMessage);
      await refresh();setTimeout(requestSnapshot,150);setTimeout(requestSnapshot,1200);timer=setInterval(refresh,Math.max(5,Number(ctx.config.refreshSeconds||15))*1000);await ctx.log('lint-away.leaderboard.ready',{boardId,frameId,maxEntries});return cleanup;
    },
    async deactivate(){cleanup()},
    async unmount(){cleanup()}
  };
}
