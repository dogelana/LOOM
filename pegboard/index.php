<?php
// @loom-file release=0.15.16 revision=27 policy=package-priority
require __DIR__.'/../api/_common.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
if(!loom_request_is_admin()){
  http_response_code(403);
  ?><!doctype html><html><head><link rel="icon" type="image/png" data-loom-favicon="1" href="../assets/loom-logo.png?v=0.15.16"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LOOM · Admin only</title><link rel="stylesheet" href="../engine/loom-design.css?v=0.15.16">
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;grid-template-rows:auto 1fr auto;font-family:Inter,system-ui;background:#eef5ef;color:#18311f}.denied{display:grid;place-items:center;padding:30px}.box{max-width:600px;padding:30px;background:#fff;border:1px solid #d8e6da;border-radius:24px;box-shadow:0 22px 65px #153b2112}a{color:#16743d}</style></head>
  <body><div id="loomShellHeader"></div><main class="denied"><div class="box"><h1>Administrator access required</h1><p>This LOOM developer page is restricted to the authenticated Admin identity.</p><a href="../home/">Return to LOOM Home</a></div></main><div id="loomShellFooter"></div>
  <script src="../engine/loom-brand.js?v=0.15.16"></script><script src="../engine/identity.js?v=0.15.16"></script><script src="../engine/loom-global-profile.js?v=0.15.16"></script><script src="../engine/loom-toast.js?v=0.15.16"></script><script src="../engine/loom-shell.js?v=0.15.16"></script>
  <script>(async()=>{const ident=LoomIdentity.get('loom-pegboard');await LoomShell.mount({apiBase:'../api',identity:ident,pageTitle:'Pegboard',links:[{label:'LOOM Home',href:'../home/'}]})})();</script></body></html><?php exit;
}
?>
<!doctype html><html lang="en"><head><link rel="icon" type="image/png" data-loom-favicon="1" href="../assets/loom-logo.png?v=0.15.16"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0"><title>LOOM Pegboard</title><link rel="stylesheet" href="pegboard.css?v=0.15.16"></head>
<body class="theme-green-beans">
<div id="loomShellHeader"></div>
<div id="pegWorkspace" class="peg-workspace">
  <div id="pegShellControls" class="tools peg-shell-controls" role="toolbar" aria-label="Pegboard controls" hidden>
    <select id="themeSelect" title="Pegboard theme"><option value="green-beans">Green Beans</option><option value="loom-dark">LOOM Dark</option></select>
    <button id="fitBtn">Fit</button>
    <button id="refreshBtn">Refresh Registry</button>
    <a id="appLink" target="_blank">Open App</a>
  </div>

  <div class="ui">
    <section class="target-panel"><div class="eyebrow">VIEWING TARGET</div><label>User / client<select id="clientSelect"><option value="">Waiting for sessions…</option></select></label><label>Session<select id="sessionSelect"><option value="">—</option></select></label><label class="follow-row"><input id="followLive" type="checkbox"> <span>Auto-follow newest live session</span></label><label>Filter modules / user actions<input id="actionFilter" class="filter-input" placeholder="shopping, recipe, create…"></label><div id="targetStatus" class="target-status">No session selected</div><div id="analytics" class="analytics"></div></section>
    <aside id="detail"><div class="empty-detail">Click a module, user-action pill, or event to inspect it.</div></aside>
    <section class="legend"><span><i class="dot available"></i>Available</span><span><i class="dot active"></i>Active / held</span><span><i class="dot stale"></i>Stale / resumable</span><span><i class="dot pulse"></i>User action</span><span><i class="dot failed"></i>Failed</span></section>
    <div id="runtimeBadge" class="runtime-badge offline">No target runtime</div>
  </div>

  <div id="viewport" class="viewport"><div id="world" class="world"><svg id="edges" class="edges"></svg><div id="nodes" class="nodes"></div></div></div>
  <div class="log-panel"><div class="log-head"><strong>Selected Session · Event History</strong><div><span id="eventCount">0 events</span><button id="clearLog">Clear view</button></div></div><div id="eventLog"></div></div>
</div>
<div id="loomShellFooter"></div>

<script src="../engine/config.js?v=0.15.16"></script>
<script src="../engine/loom-brand.js?v=0.15.16"></script>
<script src="../engine/identity.js?v=0.15.16"></script>
<script src="../engine/loom-global-profile.js?v=0.15.16"></script>
<script src="../engine/loom-toast.js?v=0.15.16"></script><script src="../engine/loom-shell.js?v=0.15.16"></script>
<script src="../engine/event-bus.js?v=0.15.16"></script>
<script src="../engine/registry-client.js?v=0.15.16"></script>
<script src="pegboard.js?v=0.15.16"></script>
<script>
(async()=>{
  const ident=LoomIdentity.get('loom-pegboard');
  await LoomShell.mount({apiBase:'../api',identity:ident,pageTitle:'Pegboard',links:[{label:'LOOM Home',href:'../home/'}]});
  const nav=document.querySelector('#loomShellHeader .loom-shell-chrome-links');
  const controls=document.getElementById('pegShellControls');
  if(nav&&controls){controls.hidden=false;nav.prepend(controls)}
})();
</script>
</body></html>
