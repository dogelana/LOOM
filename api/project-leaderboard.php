<?php
// @loom-file release=0.15.59 revision=1 policy=package-priority
declare(strict_types=1);
require_once __DIR__.'/_common.php';

function loom_leaderboard_module_id(string $board): string { return 'leaderboard.'.safe_token($board); }
function loom_leaderboard_state_normalize(array $state): array {
  $runs=is_array($state['runs']??null)?$state['runs']:[];$clean=[];foreach($runs as $id=>$value){$id=safe_token((string)$id);$v=(float)$value;if($id!==''&&is_finite($v)&&$v>=0)$clean[$id]=min($v,1.0e18);}
  return [
    'schemaVersion'=>1,
    'runs'=>$clean,
    'archivedEarned'=>max(0,(float)($state['archivedEarned']??0)),
    'lifetimeEarned'=>max(0,(float)($state['lifetimeEarned']??0)),
    'playSeconds'=>max(0,(float)($state['playSeconds']??0)),
    'active'=>(bool)($state['active']??false),
    'activeSessionId'=>safe_token((string)($state['activeSessionId']??'')),
    'lastReportEpoch'=>max(0,(float)($state['lastReportEpoch']??0)),
    'lastReason'=>substr((string)($state['lastReason']??''),0,64),
  ];
}
function loom_leaderboard_retotal(array &$state): void { $state['lifetimeEarned']=max(0,(float)$state['archivedEarned']+array_sum($state['runs'])); }
function loom_leaderboard_compact_runs(array &$state,int $keep=128): void {
  if(count($state['runs'])<=$keep)return;$drop=count($state['runs'])-$keep;$keys=array_keys($state['runs']);foreach(array_slice($keys,0,$drop) as $key){$state['archivedEarned']+=(float)$state['runs'][$key];unset($state['runs'][$key]);}
}
function loom_leaderboard_rows(string $project,string $module,array $currentOwner,int $limit): array {
  $rows=[];foreach(loom_project_state_rows_for_module($project,$module) as $row){$s=loom_leaderboard_state_normalize($row['state']??[]);loom_leaderboard_retotal($s);if($s['lifetimeEarned']<=0&&$s['playSeconds']<=0)continue;$type=(string)$row['ownerType'];$id=(string)$row['ownerId'];$name=loom_project_identity_effective_username($project,$type,$id);if(!$name)$name='Player '.strtoupper(substr(hash('sha256',$type.'|'.$id),0,6));$rows[]=['name'=>$name,'earned'=>$s['lifetimeEarned'],'playSeconds'=>(int)round($s['playSeconds']),'isCurrent'=>$currentOwner['type']===$type&&$currentOwner['id']===$id,'updatedAt'=>$row['updatedAt']??null];}
  usort($rows,function($a,$b){$cash=$b['earned']<=>$a['earned'];if($cash!==0)return $cash;$time=$b['playSeconds']<=>$a['playSeconds'];if($time!==0)return $time;return strcmp((string)($a['name']??''),(string)($b['name']??''));});$rows=array_slice($rows,0,max(1,min(100,$limit)));foreach($rows as $i=>&$row)$row['rank']=$i+1;unset($row);return $rows;
}

$method=$_SERVER['REQUEST_METHOD']??'GET';$body=[];if($method==='POST'){$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);}
$src=$method==='POST'?$body:$_GET;$clientId=safe_token((string)($src['clientId']??''));$project=safe_slug((string)($src['project']??''));$board=safe_token((string)($src['boardId']??'lint-away-game'));$limit=max(1,min(100,(int)($src['limit']??10)));
if($clientId===''||$project===''||!project_dir($project)||$board==='')json_out(['ok'=>false,'error'=>'Invalid leaderboard request'],400);loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);$owner=loom_project_state_owner($clientId);$module=loom_leaderboard_module_id($board);
if($method==='GET')json_out(['ok'=>true,'project'=>$project,'boardId'=>$board,'rows'=>loom_leaderboard_rows($project,$module,$owner,$limit),'generatedAt'=>server_timestamp()]);
if($method!=='POST')json_out(['ok'=>false,'error'=>'GET or POST required'],405);loom_global_profile_assert_mutation_access($clientId);
$runId=safe_token((string)($body['runId']??''));$sessionId=safe_token((string)($body['sessionId']??''));$reason=substr(safe_token((string)($body['reason']??'report')),0,64);$earned=$body['earned']??null;$active=(bool)($body['active']??false);
if($runId===''||$sessionId===''||!is_numeric($earned))json_out(['ok'=>false,'error'=>'Invalid game telemetry'],400);$earned=(float)$earned;if(!is_finite($earned)||$earned<0||$earned>1.0e18)json_out(['ok'=>false,'error'=>'Earned value is outside the accepted range'],400);
// Ensure this owner has a stable project-visible identity before it can appear on the board.
try{loom_project_identity_ensure($project,$clientId,true);}catch(Throwable $e){}
$current=loom_project_state_read($project,$module,$owner['type'],$owner['id']);$state=loom_leaderboard_state_normalize($current['state']??[]);$now=microtime(true);$priorAt=(float)$state['lastReportEpoch'];$priorSession=(string)$state['activeSessionId'];
// Time is counted only while the game explicitly reports itself active. Heartbeats arrive every
// 10 seconds; the 30-second cap prevents a stale/crashed tab from creating phantom playtime.
if($state['active']&&$priorAt>0&&$priorSession===$sessionId){$delta=max(0,$now-$priorAt);$state['playSeconds']+=min($delta,30.0);}
$state['runs'][$runId]=max((float)($state['runs'][$runId]??0),$earned);loom_leaderboard_compact_runs($state);loom_leaderboard_retotal($state);$state['active']=$active;$state['activeSessionId']=$sessionId;$state['lastReportEpoch']=$now;$state['lastReason']=$reason;
loom_project_state_write($project,$module,$owner['type'],$owner['id'],$state);json_out(['ok'=>true,'accepted'=>true,'lifetimeEarned'=>$state['lifetimeEarned'],'playSeconds'=>(int)round($state['playSeconds']),'rows'=>loom_leaderboard_rows($project,$module,$owner,$limit),'generatedAt'=>server_timestamp()]);
