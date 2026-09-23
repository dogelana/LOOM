<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['error'=>'Invalid JSON'],400);
$project=safe_slug((string)($body['project']??''));if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
$clientId=safe_token((string)($body['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);
$session=safe_token((string)($body['sessionId']??''));if($session==='')json_out(['error'=>'sessionId required'],400);
$now=server_epoch_ms();$lease=max(10000,min(300000,(int)($body['leaseDurationMs']??45000)));
$existing=read_json_file(presence_file($project,$session));
if($existing && ($existing['status']??'')==='closed' && ($existing['runtimeId']??null)===($body['runtimeId']??null))json_out(['ok'=>true,'ignored'=>true,'reason'=>'runtime-already-closed']);
$priorStatus=(string)($existing['status']??'');
$payload=$body;$heartbeatAt=server_timestamp();$payload['status']='active';$payload['serverTimestamp']=$heartbeatAt;$payload['lastHeartbeatAt']=$heartbeatAt;$payload['serverEpochMs']=$now;$payload['leaseDurationMs']=$lease;$payload['leaseExpiresEpochMs']=$now+$lease;
$payload['activeActions']=action_snapshot($payload);$payload['activeActionIds']=array_values(array_map(fn($a)=>$a['id'],$payload['activeActions']));
unset($payload['staleAt'],$payload['staleEpochMs'],$payload['staleReason'],$payload['expiredAt'],$payload['expiredReason']);
if($existing && in_array($priorStatus,['stale','expired'],true)){
  $base=base_identity($payload);$ids=$payload['activeActionIds'];
  append_project_event($project,$base+[
    'type'=>'session.resumed','reason'=>'heartbeat-resumed','priorStatus'=>$priorStatus,
    'resumedActionIds'=>$ids,'runtimeChanged'=>(($existing['runtimeId']??null)!==($payload['runtimeId']??null))
  ]);
  // Legacy v0.8 "expired" sessions had inferred inactive events. Restore those held states once on resume.
  if($priorStatus==='expired'){
    foreach($payload['activeActions'] as $a){append_project_event($project,$base+['type'=>'action.state','actionId'=>$a['id'],'state'=>'active','name'=>$a['name'],'kind'=>$a['kind'],'behavior'=>$a['behavior'],'reason'=>'legacy-session-resumed','inferred'=>true,'source'=>'heartbeat-resume']);}
  }
}
write_presence($project,$session,$payload);
json_out(['ok'=>true,'serverTimestamp'=>$payload['serverTimestamp'],'leaseExpiresEpochMs'=>$payload['leaseExpiresEpochMs'],'status'=>'active']);
