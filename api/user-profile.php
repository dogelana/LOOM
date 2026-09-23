<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
require __DIR__.'/_common.php';

function user_profiles_dir(): string { return loom_data_dir().'/users'; }
function user_profile_file(string $clientId): string {
  ensure_dir(user_profiles_dir());
  return user_profiles_dir().'/'.hash('sha256',$clientId).'.json';
}
function clean_username(string $value): string { return loom_clean_username($value); }
function iso_from_epoch(int $epoch): string { return gmdate('Y-m-d\\TH:i:s\\Z',$epoch); }
function hydrate_profile_timestamps(string $clientId,array $profile,array $analytics=[]): array {
  $changed=false;$file=user_profile_file($clientId);
  $created=(string)($profile['createdAt']??'');$updated=(string)($profile['updatedAt']??'');

  // Old LOOM identities may pre-date explicit profile timestamps. Prefer the
  // earliest telemetry we already know for this client, then fall back to the
  // existing profile file time, and only then to the current server time.
  if($created===''){
    $candidate=(string)($analytics['allLoom']['firstSeen']??$analytics['currentProject']['firstSeen']??'');
    if($candidate===''){
      $account=loom_account_user_for_client($clientId);
      $candidate=(string)($account['created_at']??$account['createdAt']??'');
    }
    if($candidate===''&&is_file($file)){$mtime=@filemtime($file);if($mtime)$candidate=iso_from_epoch((int)$mtime);}
    if($candidate==='')$candidate=server_timestamp();
    $profile['createdAt']=$candidate;$created=$candidate;$changed=true;
  }
  if($updated===''){
    $candidate='';
    if(is_file($file)){$mtime=@filemtime($file);if($mtime)$candidate=iso_from_epoch((int)$mtime);}
    if($candidate==='')$candidate=(string)($analytics['allLoom']['lastSeen']??$analytics['currentProject']['lastSeen']??'');
    if($candidate==='')$candidate=$created!==''?$created:server_timestamp();
    $profile['updatedAt']=$candidate;$changed=true;
  }
  $profile['_timestampsBackfilled']=$changed;
  return $profile;
}
function read_user_profile(string $clientId,string $project): array {
  $db=function_exists('loom_db_read_client_profile')?loom_db_read_client_profile($clientId):null;$file=user_profile_file($clientId);$base=is_array($db)?$db:(read_json_file($file)?:[]);if(!$base)$base=['profileId'=>'profile_'.substr(hash('sha256',$clientId),0,16),'clientId'=>$clientId,'createdAt'=>null,'updatedAt'=>null];
  $identity=loom_project_identity_ensure($project,$clientId,true);$auth=loom_auth_user();$linked=$auth?:loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');
  return ['profileId'=>$identity['identityId'],'clientProfileId'=>$base['profileId']??('profile_'.substr(hash('sha256',$clientId),0,16)),'clientId'=>$clientId,'userId'=>$uid?:null,'username'=>$identity['effectiveUsername']??loom_project_identity_effective_username_from_row($identity),'usernameMode'=>$identity['usernameMode']??'global','projectUsername'=>$identity['username']??null,'createdAt'=>$identity['createdAt']??($base['createdAt']??null),'updatedAt'=>$identity['updatedAt']??($base['updatedAt']??null),'accountEmail'=>$linked['email']??null,'accountPermanent'=>$uid!=='','project'=>$project,'projectIdentity'=>true];
}
function write_user_profile(array $profile): void {
  ensure_dir(user_profiles_dir());@file_put_contents(user_profile_file((string)$profile['clientId']),json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_client_profile')&&loom_db_ready())loom_db_write_client_profile($profile);
}
function event_ts(array $e): ?string { $v=$e['serverTimestamp']??$e['clientTimestamp']??null; return is_string($v)&&$v!==''?$v:null; }
function ts_epoch(?string $ts): ?int { if(!$ts)return null;$v=strtotime($ts);return $v===false?null:$v; }
function make_stats(): array { return ['projectCount'=>0,'sessionCount'=>0,'eventCount'=>0,'userActionCount'=>0,'uniqueActionCount'=>0,'failureCount'=>0,'activeDays'=>0,'trackedSeconds'=>0,'firstSeen'=>null,'lastSeen'=>null]; }
function collect_log_sources(): array {
  $out=[];
  $base=loom_data_dir().'/logs';
  if(is_dir($base))foreach(glob($base.'/*')?:[] as $dir){if(!is_dir($dir))continue;$slug=basename($dir);foreach(glob($dir.'/*.jsonl')?:[] as $f)$out[]=['project'=>$slug,'file'=>$f,'archived'=>false];}
  $archives=archives_root();
  if(is_dir($archives))foreach(glob($archives.'/*')?:[] as $a){if(!is_dir($a))continue;$slug=basename($a);$dir=$a.'/data/logs';if(!is_dir($dir))continue;foreach(glob($dir.'/*.jsonl')?:[] as $f)$out[]=['project'=>$slug,'file'=>$f,'archived'=>true];}
  return $out;
}
function analytics_for_client(string $clientId,string $currentProject,string $currentSession=''): array {
  $sessions=[];$projects=[];$allActions=[];$allDays=[];$top=[];
  foreach(collect_log_sources() as $source){
    $lines=@file($source['file'],FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];
    foreach($lines as $line){$e=json_decode($line,true);if(!is_array($e)||(string)($e['clientId']??'')!==$clientId)continue;
      $project=(string)$source['project'];$session=(string)($e['sessionId']??'');if($session==='')$session='unknown';$key=$project.'|'.$session;
      if(!isset($sessions[$key]))$sessions[$key]=['project'=>$project,'sessionId'=>$session,'firstSeen'=>null,'lastSeen'=>null,'eventCount'=>0,'userActionCount'=>0,'failureCount'=>0,'status'=>'historical','archived'=>(bool)$source['archived'],'actions'=>[]];
      $s=&$sessions[$key];$ts=event_ts($e);if($ts){$s['firstSeen']=$s['firstSeen']===null?$ts:min($s['firstSeen'],$ts);$s['lastSeen']=$s['lastSeen']===null?$ts:max($s['lastSeen'],$ts);$day=substr($ts,0,10);if($day)$allDays[$day]=true;}
      $s['eventCount']++;
      $aid=(string)($e['actionId']??'');if($aid!==''){$s['actions'][$aid]=true;$allActions[$aid]=true;}
      $isUser=(($e['type']??'')==='action.state'&&($e['kind']??'')==='user'&&($e['state']??'')==='active');
      if($isUser){$s['userActionCount']++;if($aid!=='')$top[$aid]=($top[$aid]??0)+1;}
      if(($e['state']??'')==='failed'||str_contains((string)($e['type']??''),'error'))$s['failureCount']++;
      if(($e['type']??'')==='session.end')$s['status']='closed';
      if(($e['type']??'')==='session.stale')$s['status']='stale';
      if(($e['type']??'')==='session.resumed')$s['status']='historical';
      $projects[$project]=true;unset($s);
    }
  }
  // Presence is authoritative for current active/stale state.
  $pdir=presence_dir($currentProject);
  if(is_dir($pdir))foreach(glob($pdir.'/*.json')?:[] as $f){$p=read_json_file($f);if(!$p||(string)($p['clientId']??'')!==$clientId)continue;$sid=(string)($p['sessionId']??'');if($sid==='')continue;$key=$currentProject.'|'.$sid;
    if(!isset($sessions[$key]))$sessions[$key]=['project'=>$currentProject,'sessionId'=>$sid,'firstSeen'=>$p['serverTimestamp']??null,'lastSeen'=>$p['lastHeartbeatAt']??$p['serverTimestamp']??null,'eventCount'=>0,'userActionCount'=>0,'failureCount'=>0,'status'=>'historical','archived'=>false,'actions'=>[]];
    $sessions[$key]['status']=(($p['status']??'')==='active')?'live':(string)($p['status']??'historical');$sessions[$key]['lastSeen']=$p['lastHeartbeatAt']??$sessions[$key]['lastSeen'];$projects[$currentProject]=true;
  }
  $all=make_stats();$current=make_stats();$currentActions=[];$currentDays=[];$currentProjects=[];
  foreach($sessions as &$s){$a=ts_epoch($s['firstSeen']);$b=ts_epoch($s['lastSeen']);$s['durationSeconds']=($a!==null&&$b!==null)?max(0,$b-$a):0;$s['uniqueActionCount']=count($s['actions']);unset($s['actions']);
    $all['sessionCount']++;$all['eventCount']+=$s['eventCount'];$all['userActionCount']+=$s['userActionCount'];$all['failureCount']+=$s['failureCount'];$all['trackedSeconds']+=$s['durationSeconds'];
    if($s['firstSeen'])$all['firstSeen']=$all['firstSeen']===null?$s['firstSeen']:min($all['firstSeen'],$s['firstSeen']);if($s['lastSeen'])$all['lastSeen']=$all['lastSeen']===null?$s['lastSeen']:max($all['lastSeen'],$s['lastSeen']);
    if($s['project']===$currentProject){$current['sessionCount']++;$current['eventCount']+=$s['eventCount'];$current['userActionCount']+=$s['userActionCount'];$current['failureCount']+=$s['failureCount'];$current['trackedSeconds']+=$s['durationSeconds'];$currentProjects[$s['project']]=true;if($s['firstSeen']){$current['firstSeen']=$current['firstSeen']===null?$s['firstSeen']:min($current['firstSeen'],$s['firstSeen']);$currentDays[substr($s['firstSeen'],0,10)]=true;}if($s['lastSeen']){$current['lastSeen']=$current['lastSeen']===null?$s['lastSeen']:max($current['lastSeen'],$s['lastSeen']);$currentDays[substr($s['lastSeen'],0,10)]=true;}}
  }unset($s);
  foreach(collect_log_sources() as $source){if($source['project']!==$currentProject)continue;$lines=@file($source['file'],FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];foreach($lines as $line){$e=json_decode($line,true);if(!is_array($e)||(string)($e['clientId']??'')!==$clientId)continue;$aid=(string)($e['actionId']??'');if($aid!=='')$currentActions[$aid]=true;$ts=event_ts($e);if($ts)$currentDays[substr($ts,0,10)]=true;}}
  $all['projectCount']=count($projects);$all['uniqueActionCount']=count($allActions);$all['activeDays']=count($allDays);
  $current['projectCount']=count($currentProjects);$current['uniqueActionCount']=count($currentActions);$current['activeDays']=count(array_filter($currentDays,fn($k)=>$k!=='',ARRAY_FILTER_USE_KEY));
  uasort($top,fn($a,$b)=>$b<=>$a);$topRows=[];foreach(array_slice($top,0,12,true) as $id=>$count)$topRows[]=['actionId'=>$id,'count'=>$count];
  $sessionRows=array_values($sessions);usort($sessionRows,fn($a,$b)=>strcmp((string)($b['lastSeen']??''),(string)($a['lastSeen']??'')));
  return ['currentProject'=>$current,'allLoom'=>$all,'recentSessions'=>array_slice($sessionRows,0,12),'topUserActions'=>$topRows,'currentSessionId'=>$currentSession];
}

$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST'){
  $body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['error'=>'Invalid JSON'],400);
  $clientId=safe_token((string)($body['clientId']??''));$project=safe_slug((string)($body['project']??''));$username=clean_username((string)($body['username']??''));
  if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['error'=>'Invalid client identity'],400);
  if($username==='')json_out(['error'=>'Username cannot be empty once identity naming is used.'],400);
  if($project!==''&&!project_dir($project))json_out(['error'=>'Invalid project'],400);
  if($project!==''){loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);}


  $scope=(string)($body['scope']??'project');$mode=(string)($body['mode']??'');
  try{
    if($scope==='global'){$global=loom_global_profile_set_username($clientId,$username);$identity=loom_project_identity_ensure($project,$clientId,true);}
    elseif($mode==='global'){$identity=loom_project_identity_set_username_mode($project,$clientId,'global');$global=loom_global_profile_ensure($clientId);}
    else{$identity=loom_project_identity_set_username($project,$clientId,$username);$global=loom_global_profile_ensure($clientId);}
  }catch(Throwable $e){json_out(['error'=>$e->getMessage()],409);}
  $profile=read_user_profile($clientId,$project);$priv=loom_bootstrap_or_privilege($clientId,true);$profile['privilege']=$priv['privilege'];
  json_out(['ok'=>true,'profile'=>$profile,'globalProfile'=>$global??loom_global_profile_ensure($clientId),'projectIdentity'=>$identity,'identities'=>loom_project_identity_list_for_client($clientId),'privilege'=>$priv,'account'=>loom_account_public_status($clientId,$project)]);
}
if($method!=='GET')json_out(['error'=>'GET or POST required'],405);
$clientId=safe_token((string)($_GET['clientId']??''));$project=safe_slug((string)($_GET['project']??''));$sessionId=safe_token((string)($_GET['sessionId']??''));
if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['error'=>'Invalid client identity'],400);
if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);
$profile=read_user_profile($clientId,$project);$priv=loom_bootstrap_or_privilege($clientId,true);$profile['privilege']=$priv['privilege'];$network=loom_network_state_for_client($clientId,$project);
$analytics=analytics_for_client($clientId,$project,$sessionId);
$beforeCreated=(string)($profile['createdAt']??'');$beforeUpdated=(string)($profile['updatedAt']??'');
$profile=hydrate_profile_timestamps($clientId,$profile,$analytics);

json_out([
  'ok'=>true,'profile'=>$profile,'globalProfile'=>loom_global_profile_ensure($clientId),'projectIdentity'=>loom_project_identity_ensure($project,$clientId,true),'identities'=>loom_project_identity_list_for_client($clientId),'privilege'=>$priv,'account'=>loom_account_public_status($clientId,$project),
  'storage'=>['mode'=>loom_db_ready()?'database':'durable-local','database'=>loom_db_status()],
  'network'=>$network,
  'projectAccess'=>loom_project_access_status($project,$clientId),
  'analytics'=>$analytics,
  'privacy'=>[
    'identityBasis'=>'password-backed LOOM account plus a global LOOM profile; each project may inherit or independently override username/profile picture; IP is network metadata only',
    'rawIpStored'=>true,
    'note'=>loom_db_ready()
      ?'Persistent SQL storage is active. Email/password identifies the LOOM account; the LOOM profile supplies global username/profile picture defaults and projects may override either independently.'
      :'Database is not connected yet. Server profiles/accounts are temporary local files until an administrator connects MySQL/MariaDB and migrates them.'
  ]
]);
