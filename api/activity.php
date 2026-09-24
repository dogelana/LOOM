<?php
// @loom-file release=0.15.10 revision=1 policy=package-priority
declare(strict_types=1);
require __DIR__.'/_common.php';

if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);

$project=safe_slug((string)($_GET['project']??''));
if($project===''||!project_dir($project))json_out(['ok'=>false,'error'=>'invalid-project'],400);

$subjectType=(string)($_GET['subjectType']??'');
if(!in_array($subjectType,['','user','client'],true))$subjectType='';
$subjectId=safe_token((string)($_GET['subjectId']??''));
$sessionId=safe_token((string)($_GET['sessionId']??''));
$category=(string)($_GET['category']??'all');
if(!in_array($category,['all','actions','interactions','sessions','errors','modules','framed'],true))$category='all';
$query=trim((string)($_GET['q']??''));
$query=substr($query,0,160);
$limit=max(25,min(2000,(int)($_GET['limit']??500)));

function loom_activity_time_bound(string $value,bool $end=false): ?int {
  $value=trim($value);if($value==='')return null;
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))$value.=$end?' 23:59:59':' 00:00:00';
  $t=strtotime($value);return $t===false?null:$t;
}
$from=loom_activity_time_bound((string)($_GET['from']??''),false);
$to=loom_activity_time_bound((string)($_GET['to']??''),true);

function loom_activity_linked_clients(string $userId): array {
  $out=[];$temp=loom_temp_account_store();
  foreach(($temp['clients']??[]) as $cid=>$uid)if((string)$uid===$userId)$out[(string)$cid]=true;
  if(loom_db_ready())try{
    $st=loom_db_pdo(true)->prepare("SELECT client_id FROM loom_user_clients WHERE user_id=?");
    $st->execute([$userId]);foreach($st->fetchAll() as $r)$out[(string)$r['client_id']]=true;
  }catch(Throwable $e){}
  return array_keys($out);
}
$linkedClients=$subjectType==='user'&&$subjectId!==''?loom_activity_linked_clients($subjectId):[];
$linkedSet=array_fill_keys($linkedClients,true);

function loom_activity_subject_match(array $row,string $type,string $id,array $linkedSet): bool {
  if($type===''||$id==='')return true;
  if($type==='client')return (string)($row['clientId']??'')===$id;
  return (string)($row['userId']??'')===$id||isset($linkedSet[(string)($row['clientId']??'')]);
}
function loom_activity_ts(array $row): string {
  return (string)($row['serverTimestamp']??$row['clientTimestamp']??$row['createdAt']??'');
}
function loom_activity_in_range(string $ts,?int $from,?int $to): bool {
  if($ts==='')return $from===null&&$to===null;
  $t=strtotime($ts);if($t===false)return false;
  if($from!==null&&$t<$from)return false;if($to!==null&&$t>$to)return false;return true;
}
function loom_activity_category_for_event(array $e): string {
  $type=(string)($e['type']??'');$aid=(string)($e['actionId']??'');$state=(string)($e['state']??'');
  if(str_starts_with($aid,'html.frame.')||str_starts_with($type,'html-framer.'))return 'framed';
  if($state==='failed'||str_contains($type,'error'))return 'errors';
  if(str_starts_with($type,'session.'))return 'sessions';
  if($type==='action.state'&&($e['kind']??'')==='user')return 'actions';
  if(str_starts_with($type,'module.')||($type==='action.state'&&($e['kind']??'')!=='user'))return 'modules';
  return 'events';
}
function loom_activity_summary_event(array $e): string {
  $type=(string)($e['type']??'event');$name=(string)($e['name']??'');$aid=(string)($e['actionId']??'');$state=(string)($e['state']??'');
  if($type==='action.state')return trim(($name?:$aid?:'Action').($state!==''?' · '.$state:''));
  if(str_starts_with($type,'session.'))return $type.(isset($e['reason'])?' · '.(string)$e['reason']:'');
  if(str_starts_with($type,'module.'))return $type.($name!==''?' · '.$name:($aid!==''?' · '.$aid:''));
  return $type.($aid!==''?' · '.$aid:'');
}
function loom_activity_search_match(array $row,string $query): bool {
  if($query==='')return true;
  $hay=strtolower((string)json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  return str_contains($hay,strtolower($query));
}

$rows=[];$allCount=0;$counts=['actions'=>0,'interactions'=>0,'sessions'=>0,'errors'=>0,'modules'=>0,'framed'=>0,'events'=>0];$sessions=[];
$files=is_dir(log_dir($project))?(glob(log_dir($project).'/*.jsonl')?:[]):[];sort($files);
foreach($files as $file){
  foreach(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
    $e=json_decode($line,true);if(!is_array($e))continue;
    if(!loom_project_record_visible($project,(string)($e['clientId']??''),(string)($e['userId']??'')))continue;
    if(!loom_activity_subject_match($e,$subjectType,$subjectId,$linkedSet))continue;
    $sid=(string)($e['sessionId']??'');if($sessionId!==''&&$sid!==$sessionId)continue;
    $ts=loom_activity_ts($e);if(!loom_activity_in_range($ts,$from,$to))continue;
    $cat=loom_activity_category_for_event($e);
    if($category!=='all'&&$category!==$cat)continue;
    $row=[
      'id'=>(string)($e['id']??event_id('activity')),
      'source'=>$cat==='framed'?'framed':'event','category'=>$cat,'timestamp'=>$ts,
      'project'=>$project,'sessionId'=>$sid?:null,'clientId'=>$e['clientId']??null,'userId'=>$e['userId']??null,'userLabel'=>$e['userLabel']??null,
      'type'=>$e['type']??null,'actionId'=>$e['actionId']??null,'state'=>$e['state']??null,'kind'=>$e['kind']??null,
      'summary'=>loom_activity_summary_event($e),
      'detail'=>array_filter([
        'reason'=>$e['reason']??null,'message'=>$e['message']??null,'domainEvent'=>$e['domainEvent']??null,
        'source'=>$e['source']??null,'stepId'=>$e['stepId']??null,
        'framedEventType'=>$e['framedEventType']??null,'framedLabel'=>$e['framedLabel']??null,'framedTarget'=>$e['framedTarget']??null,
        'x'=>$e['x']??null,'y'=>$e['y']??null,'button'=>$e['button']??null,'href'=>$e['href']??null,'method'=>$e['method']??null,
        'checked'=>$e['checked']??null,'selectedIndex'=>$e['selectedIndex']??null,'fileCount'=>$e['fileCount']??null
      ],fn($v)=>$v!==null&&$v!=='')
    ];
    if(!loom_activity_search_match($row,$query))continue;
    $rows[]=$row;$allCount++;$counts[$cat]=($counts[$cat]??0)+1;if($sid!=='')$sessions[$sid]=true;
  }
}

$replayDir=loom_data_dir().'/replay/'.$project;
$replayFiles=is_dir($replayDir)?(glob($replayDir.'/*.jsonl')?:[]):[];sort($replayFiles);
if(in_array($category,['all','interactions'],true)){
  foreach($replayFiles as $file){
    foreach(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
      $r=json_decode($line,true);if(!is_array($r))continue;
      if(!loom_activity_subject_match($r,$subjectType,$subjectId,$linkedSet))continue;
      $sid=(string)($r['sessionId']??'');if($sessionId!==''&&$sid!==$sessionId)continue;
      $ts=(string)($r['serverTimestamp']??'');if(!loom_activity_in_range($ts,$from,$to))continue;
      $ev=is_array($r['event']??null)?$r['event']:[];
      $itype=(string)($ev['type']??'interaction');
      $target=(string)($ev['target']??'');
      $summary=ucwords(str_replace('-',' ',$itype)).($target!==''?' · '.$target:'');
      $row=[
        'id'=>(string)($r['replayId']??event_id('activity')),
        'source'=>'interaction','category'=>'interactions','timestamp'=>$ts,'project'=>$project,
        'sessionId'=>$sid?:null,'clientId'=>$r['clientId']??null,'userId'=>$r['userId']??null,'userLabel'=>null,
        'type'=>'interaction.'.$itype,'actionId'=>null,'state'=>null,'kind'=>'interaction','summary'=>$summary,
        'detail'=>$ev
      ];
      if(!loom_activity_search_match($row,$query))continue;
      $rows[]=$row;$allCount++;$counts['interactions']++;if($sid!=='')$sessions[$sid]=true;
    }
  }
}

usort($rows,function($a,$b){return strcmp((string)($b['timestamp']??''),(string)($a['timestamp']??''));});
$limited=array_slice($rows,0,$limit);
json_out([
  'ok'=>true,'project'=>$project,'subjectType'=>$subjectType?:null,'subjectId'=>$subjectId?:null,
  'rows'=>$limited,'matched'=>$allCount,'returned'=>count($limited),'limit'=>$limit,'counts'=>$counts,
  'sessions'=>array_values(array_keys($sessions)),'generatedAt'=>server_timestamp(),
  'note'=>'Activity Explorer combines semantic LOOM events with privacy-scrubbed interaction capture. Sensitive inputs remain masked/omitted.'
]);
