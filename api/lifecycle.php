<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['error'=>'Invalid JSON'],400);
$project=safe_slug((string)($body['project']??''));if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
$clientId=safe_token((string)($body['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);
$session=safe_token((string)($body['sessionId']??''));if($session==='')json_out(['error'=>'sessionId required'],400);
$event=(string)($body['event']??'close');if($event!=='close')json_out(['error'=>'Unsupported lifecycle event'],400);
$existing=read_json_file(presence_file($project,$session));
if($existing && ($existing['status']??'')==='closed' && !empty($body['closeToken']) && ($existing['closeToken']??null)===$body['closeToken'])json_out(['ok'=>true,'deduped'=>true]);
$presence=$existing?:$body;$presence=array_replace($presence,$body);$presence['activeActions']=action_snapshot($body ?: $presence);$presence['activeActionIds']=array_values(array_map(fn($a)=>$a['id'],$presence['activeActions']));
$reason=(string)($body['reason']??'graceful-close');
emit_inactive_snapshot($project,$presence,$reason,false);
$end=append_project_event($project,base_identity($presence)+['type'=>'session.end','reason'=>$reason,'graceful'=>true,'source'=>'lifecycle-close']);
$presence['status']='closed';$presence['closedAt']=$end['serverTimestamp'];$presence['closedReason']=$reason;$presence['closeToken']=$body['closeToken']??null;$presence['activeActionIds']=[];$presence['activeActions']=[];$presence['serverTimestamp']=$end['serverTimestamp'];$presence['serverEpochMs']=$end['serverEpochMs'];$presence['leaseExpiresEpochMs']=$end['serverEpochMs'];
write_presence($project,$session,$presence);
json_out(['ok'=>true,'serverTimestamp'=>$end['serverTimestamp']]);
