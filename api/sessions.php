<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);
$project=safe_slug((string)($_GET['project']??''));if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
mark_stale_sessions($project);
$clients=[];$sessions=[];
function ensure_session(array &$sessions,string $client,string $session): void {
  if(!isset($sessions[$session]))$sessions[$session]=[
    'sessionId'=>$session,'clientId'=>$client,'userId'=>null,'userLabel'=>null,'firstSeen'=>null,'lastSeen'=>null,
    'eventCount'=>0,'failureCount'=>0,'userActionCount'=>0,'uniqueActions'=>[],'meta'=>null,'runtimeId'=>null,
    'active'=>false,'status'=>'historical','activeActionIds'=>[],'activeActions'=>[],
    'closedReason'=>null,'expiredReason'=>null,'staleReason'=>null,'staleAt'=>null,'staleForMs'=>0,
    'leaseExpiresEpochMs'=>null,'serverEpochMs'=>null
  ];
}
$dir=log_dir($project);$files=is_dir($dir)?(glob($dir.'/*.jsonl')?:[]):[];sort($files);
foreach($files as $file){
  $lines=@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
  foreach($lines as $line){
    $e=json_decode($line,true);if(!is_array($e))continue;if(!loom_project_record_visible($project,(string)($e['clientId']??''),(string)($e['userId']??'')))continue;
    $client=(string)($e['clientId']??'');$session=(string)($e['sessionId']??'');if($client===''||$session==='')continue;
    ensure_session($sessions,$client,$session);$s=&$sessions[$session];
    $ts=(string)($e['serverTimestamp']??$e['clientTimestamp']??'');if($ts!==''){$s['firstSeen']=$s['firstSeen']===null?$ts:min($s['firstSeen'],$ts);$s['lastSeen']=$s['lastSeen']===null?$ts:max($s['lastSeen'],$ts);}
    $s['eventCount']++;$s['userId']=$e['userId']??$s['userId'];$s['userLabel']=$e['userLabel']??$s['userLabel'];$s['runtimeId']=$e['runtimeId']??$s['runtimeId'];
    if(($e['type']??'')==='session.start'&&isset($e['meta']))$s['meta']=$e['meta'];
    if(($e['state']??'')==='failed'||str_contains((string)($e['type']??''),'error'))$s['failureCount']++;
    if(($e['type']??'')==='action.state'&&($e['kind']??'')==='user'&&($e['state']??'')==='active')$s['userActionCount']++;
    if(!empty($e['actionId']))$s['uniqueActions'][(string)$e['actionId']]=true;
    if(($e['type']??'')==='session.end')$s['closedReason']=$e['reason']??$s['closedReason'];
    if(($e['type']??'')==='session.expired')$s['expiredReason']=$e['reason']??'heartbeat-timeout'; // legacy v0.8 only
    if(($e['type']??'')==='session.stale'){$s['staleReason']=$e['reason']??'heartbeat-missed';$s['staleAt']=$e['staleAt']??$e['serverTimestamp']??$s['staleAt'];}
    if(($e['type']??'')==='session.resumed'){$s['staleReason']=null;$s['staleAt']=null;$s['staleForMs']=0;}
    unset($s);
  }
}
$presenceDir=presence_dir($project);$now=server_epoch_ms();
foreach(is_dir($presenceDir)?(glob($presenceDir.'/*.json')?:[]):[] as $file){
  $p=read_json_file($file);if(!$p)continue;if(!loom_project_record_visible($project,(string)($p['clientId']??''),(string)($p['userId']??'')))continue;$client=(string)($p['clientId']??'');$session=(string)($p['sessionId']??'');if($client===''||$session==='')continue;
  ensure_session($sessions,$client,$session);$s=&$sessions[$session];
  $s['userId']=$p['userId']??$s['userId'];$s['userLabel']=$p['userLabel']??$s['userLabel'];$s['runtimeId']=$p['runtimeId']??$s['runtimeId'];$s['meta']=$p['meta']??$s['meta'];
  $s['lastSeen']=$p['lastHeartbeatAt']??$p['serverTimestamp']??$s['lastSeen'];$s['serverEpochMs']=$p['serverEpochMs']??null;$s['leaseExpiresEpochMs']=$p['leaseExpiresEpochMs']??null;
  $status=(string)($p['status']??'historical');$s['active']=$status==='active';$s['status']=$s['active']?'live':$status;
  $s['activeActionIds']=array_values($p['activeActionIds']??[]);$s['activeActions']=array_values($p['activeActions']??[]);
  $s['closedReason']=$p['closedReason']??$s['closedReason'];$s['expiredReason']=$p['expiredReason']??$s['expiredReason'];
  $s['staleReason']=$p['staleReason']??$s['staleReason'];$s['staleAt']=$p['staleAt']??$s['staleAt'];
  $staleEpoch=(int)($p['staleEpochMs']??0);$s['staleForMs']=($status==='stale'&&$staleEpoch>0)?max(0,$now-$staleEpoch):0;
  $s['leaseRemainingMs']=$s['active']?max(0,(int)$s['leaseExpiresEpochMs']-$now):0;
  unset($s);
}
foreach($sessions as &$s){
  $s['uniqueActionCount']=count($s['uniqueActions']);$s['uniqueActions']=array_keys($s['uniqueActions']);
  $start=$s['firstSeen']?strtotime($s['firstSeen']):false;$end=$s['lastSeen']?strtotime($s['lastSeen']):false;$s['durationSeconds']=($start!==false&&$end!==false)?max(0,$end-$start):0;
  $client=$s['clientId'];if(!isset($clients[$client]))$clients[$client]=['clientId'=>$client,'userId'=>$s['userId'],'userLabel'=>$s['userLabel'],'firstSeen'=>$s['firstSeen'],'lastSeen'=>$s['lastSeen'],'sessionCount'=>0,'eventCount'=>0,'active'=>false,'stale'=>false,'sessions'=>[]];
  $c=&$clients[$client];$c['userId']=$s['userId']??$c['userId'];$c['userLabel']=$s['userLabel']??$c['userLabel'];
  $c['firstSeen']=$c['firstSeen']===null?$s['firstSeen']:min($c['firstSeen'],$s['firstSeen']);$c['lastSeen']=$c['lastSeen']===null?$s['lastSeen']:max($c['lastSeen'],$s['lastSeen']);
  $c['sessionCount']++;$c['eventCount']+=$s['eventCount'];$c['active']=$c['active']||$s['active'];$c['stale']=$c['stale']||$s['status']==='stale';$c['sessions'][]=$s;unset($c);
}
unset($s);
$rank=fn($s)=>(($s['active']??false)?2:(($s['status']??'')==='stale'?1:0));
foreach($clients as &$c){
  usort($c['sessions'],function($a,$b)use($rank){$r=$rank($b)<=>$rank($a);return $r!==0?$r:strcmp((string)$b['lastSeen'],(string)$a['lastSeen']);});
  $c['latestSession']=$c['sessions'][0]??null;if(!$c['userLabel']){$short=strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/','',$c['clientId']),-8));$c['userLabel']='Anonymous '.$short;}
}
unset($c);
$out=array_values($clients);usort($out,function($a,$b){$ra=$a['active']?2:($a['stale']?1:0);$rb=$b['active']?2:($b['stale']?1:0);if($ra!==$rb)return $rb<=>$ra;return strcmp((string)$b['lastSeen'],(string)$a['lastSeen']);});
json_out(['project'=>$project,'generated_at'=>server_timestamp(),'clients'=>$out]);
