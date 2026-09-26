<?php
// @loom-file release=0.15.58 revision=63 policy=package-priority
require __DIR__.'/../api/_common.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$loomPageMeta=loom_generic_social_meta(loom_absolute_web_url(rtrim(web_base_path(),'/').'/pegboard/'),'LOOM Pegboard','LOOM developer pegboard and live module inspection.');$loomPageMeta['robots']='noindex,nofollow';$loomPageSocial=loom_social_meta_html($loomPageMeta,false);
loom_native_admin_page_guard('Pegboard','../');
?>
<!doctype html><html lang="en"><head><?php echo $loomPageSocial; ?><link rel="icon" type="image/png" data-loom-favicon="1" href="../assets/loom-logo.png?v=0.15.58"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0"><link rel="stylesheet" href="pegboard.css?v=0.15.58"></head>
<body class="theme-loom-dark">
<div id="loomShellHeader"></div>
<div id="pegWorkspace" class="peg-workspace">
  <div id="pegShellControls" class="tools peg-shell-controls" role="toolbar" aria-label="Pegboard controls" hidden>
    <select id="themeSelect" title="Pegboard theme"><option value="loom-dark">LOOM Dark</option></select>
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

<script src="../engine/config.js?v=0.15.58"></script>
<script src="../engine/loom-brand.js?v=0.15.58"></script>
<script src="../engine/identity.js?v=0.15.58"></script>
<script src="../engine/loom-global-profile.js?v=0.15.58"></script>
<script src="../engine/loom-toast.js?v=0.15.58"></script><script src="../engine/share-referrals.js?v=0.15.58"></script><script src="../engine/loom-shell.js?v=0.15.58"></script>
<script src="../engine/event-bus.js?v=0.15.58"></script>
<script src="../engine/registry-client.js?v=0.15.58"></script>
<script src="pegboard.js?v=0.15.58"></script>
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
