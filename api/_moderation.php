<?php
// @loom-file release=0.15.54 revision=5 policy=package-priority
declare(strict_types=1);

function loom_network_file(): string { return loom_data_dir().'/users/network.json'; }
function loom_moderation_file(): string { return loom_data_dir().'/admin/moderation.json'; }

function loom_network_store(): array {
  $d=read_json_file(loom_network_file())?:[];
  return array_replace(['schemaVersion'=>'1.0','identities'=>[]],$d);
}
function loom_write_network_store(array $store): void {
  ensure_dir(dirname(loom_network_file()));$store['schemaVersion']='1.0';$store['updatedAt']=server_timestamp();
  @file_put_contents(loom_network_file(),json_encode($store,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function loom_moderation_store(): array {
  $d=read_json_file(loom_moderation_file())?:[];
  return array_replace(['schemaVersion'=>'1.0','projectStates'=>[],'audit'=>[]],$d);
}
function loom_write_moderation_store(array $store): void {
  ensure_dir(dirname(loom_moderation_file()));$store['schemaVersion']='1.0';$store['updatedAt']=server_timestamp();
  @file_put_contents(loom_moderation_file(),json_encode($store,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function loom_valid_ip(?string $value): ?string {
  $value=trim((string)$value);if($value==='')return null;
  // Normalize common proxy representations without changing the exact network identity.
  if(preg_match('/^\[([^\]]+)\](?::\d+)?$/',$value,$m))$value=$m[1];
  if(preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/',$value,$m))$value=$m[1];
  if(preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i',$value,$m) && filter_var($m[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return $m[1];
  if(!filter_var($value,FILTER_VALIDATE_IP))return null;
  if(function_exists('inet_pton')&&function_exists('inet_ntop')){
    $packed=@inet_pton($value);if($packed!==false){$normalized=@inet_ntop($packed);if(is_string($normalized)&&$normalized!=='')$value=$normalized;}
  }
  return strtolower($value);
}

function loom_ip_network_key(?string $value): string {
  $ip=loom_valid_ip($value);if(!$ip)return '';
  if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return 'ipv4:'.$ip;
  if(!function_exists('inet_pton')||!function_exists('inet_ntop'))return 'ipv6:'.$ip;
  $packed=@inet_pton($ip);if($packed===false||strlen($packed)!==16)return 'ipv6:'.$ip;
  $prefix=substr($packed,0,8).str_repeat("\0",8);$display=@inet_ntop($prefix);
  return 'ipv6-64:'.strtolower(is_string($display)&&$display!==''?$display:$ip);
}
function loom_ip_network_label(?string $value): string {
  $ip=loom_valid_ip($value);if(!$ip)return '';
  if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))return 'Shared public IPv4 · '.$ip;
  $key=loom_ip_network_key($ip);$prefix=str_starts_with($key,'ipv6-64:')?substr($key,10):$ip;return 'IPv6 /64 network · '.$prefix.'/64';
}
function loom_request_geo_hint(): array {
  $first=function(array $keys): string {foreach($keys as $k){$v=trim((string)($_SERVER[$k]??''));if($v!=='')return $v;}return '';};
  $countryCode=strtoupper($first(['HTTP_CF_IPCOUNTRY','GEOIP_COUNTRY_CODE','HTTP_X_COUNTRY_CODE']));if(!preg_match('/^[A-Z]{2}$/',$countryCode))$countryCode='';
  $country=$first(['GEOIP_COUNTRY_NAME','HTTP_X_COUNTRY_NAME']);$region=$first(['HTTP_CF_REGION','GEOIP_REGION_NAME','HTTP_X_REGION']);$city=$first(['HTTP_CF_CITY','GEOIP_CITY','HTTP_X_CITY']);
  return ['countryCode'=>$countryCode?:null,'country'=>$country?:null,'region'=>$region?:null,'city'=>$city?:null];
}
function loom_ip_version(?string $value): ?string { $ip=loom_valid_ip($value);if(!$ip)return null;return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)?'IPv4':'IPv6'; }
function loom_ip_display(?string $value): string { return loom_valid_ip($value)?:''; }
function loom_ip_is_public(string $ip): bool { return (bool)filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE); }
function loom_request_ip_observation(): array {
  $candidates=[];
  if(!empty($_SERVER['HTTP_CF_CONNECTING_IP']))$candidates[]=['value'=>(string)$_SERVER['HTTP_CF_CONNECTING_IP'],'source'=>'CF-Connecting-IP'];
  if(!empty($_SERVER['HTTP_X_REAL_IP']))$candidates[]=['value'=>(string)$_SERVER['HTTP_X_REAL_IP'],'source'=>'X-Real-IP'];
  if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))foreach(explode(',',(string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $part)$candidates[]=['value'=>trim($part),'source'=>'X-Forwarded-For'];
  if(!empty($_SERVER['REMOTE_ADDR']))$candidates[]=['value'=>(string)$_SERVER['REMOTE_ADDR'],'source'=>'REMOTE_ADDR'];
  $valid=[];foreach($candidates as $c){$ip=loom_valid_ip($c['value']);if($ip)$valid[]=['ip'=>$ip,'source'=>$c['source'],'version'=>loom_ip_version($ip),'public'=>loom_ip_is_public($ip)];}
  foreach($valid as $v)if($v['public']&&$v['version']==='IPv4')return $v;
  foreach($valid as $v)if($v['public']&&$v['version']==='IPv6')return $v;
  return $valid[0]??['ip'=>null,'source'=>null,'version'=>null,'public'=>false];
}
function loom_moderation_db(): ?PDO {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;static $ready=false;if($ready)return $pdo;
  try{
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_identity_ips (owner_type ENUM('client','user') NOT NULL,owner_id VARCHAR(96) NOT NULL,ip_address VARCHAR(45) NOT NULL,network_key VARCHAR(96) NULL,first_seen DATETIME(3) NOT NULL,last_seen DATETIME(3) NOT NULL,seen_count BIGINT UNSIGNED NOT NULL DEFAULT 1,last_project_slug VARCHAR(96) NULL,source VARCHAR(64) NULL,PRIMARY KEY(owner_type,owner_id,ip_address),INDEX idx_identity_ip_last_seen(last_seen),INDEX idx_identity_ip_address(ip_address),INDEX idx_identity_network_key(network_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_project_user_state (project_slug VARCHAR(96) NOT NULL,subject_type ENUM('client','user') NOT NULL,subject_id VARCHAR(96) NOT NULL,banned TINYINT(1) NOT NULL DEFAULT 0,include_data TINYINT(1) NOT NULL DEFAULT 0,banned_at DATETIME(3) NULL,banned_by_user_id VARCHAR(96) NULL,updated_at DATETIME(3) NOT NULL,PRIMARY KEY(project_slug,subject_type,subject_id),INDEX idx_project_banned(project_slug,banned),INDEX idx_subject_state(subject_type,subject_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_admin_audit (audit_id VARCHAR(96) PRIMARY KEY,admin_user_id VARCHAR(96) NULL,admin_client_id VARCHAR(96) NULL,action_type VARCHAR(96) NOT NULL,target_type VARCHAR(32) NULL,target_id VARCHAR(96) NULL,project_slug VARCHAR(96) NULL,created_at DATETIME(3) NOT NULL,payload JSON NULL,INDEX idx_admin_audit_time(created_at),INDEX idx_admin_audit_target(target_type,target_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try{$cols=$pdo->query("SHOW COLUMNS FROM loom_identity_ips LIKE 'network_key'")->fetchAll();if(!$cols)$pdo->exec("ALTER TABLE loom_identity_ips ADD COLUMN network_key VARCHAR(96) NULL AFTER ip_address, ADD INDEX idx_identity_network_key(network_key)");}catch(Throwable $ignored){}
    $ready=true;
  }catch(Throwable $e){return null;}
  return $pdo;
}
function loom_record_identity_ip(string $ownerType,string $ownerId,string $ip,string $source,?string $project=null): void {
  if(!in_array($ownerType,['client','user'],true)||$ownerId===''||!loom_valid_ip($ip))return;
  $key=$ownerType.':'.$ownerId;$now=server_timestamp();$store=loom_network_store();$row=$store['identities'][$key]??['ownerType'=>$ownerType,'ownerId'=>$ownerId,'addresses'=>[]];
  $prior=$row['addresses'][$ip]??null;$row['addresses'][$ip]=[
    'ip'=>$ip,'source'=>$source,'networkKey'=>loom_ip_network_key($ip),'geoHint'=>array_filter(array_replace((array)($prior['geoHint']??[]),loom_request_geo_hint()),fn($v)=>$v!==null&&$v!==''),'firstSeen'=>$prior['firstSeen']??$now,'lastSeen'=>$now,
    'seenCount'=>(int)($prior['seenCount']??0)+1,'lastProject'=>$project?:($prior['lastProject']??null)
  ];
  $row['lastIp']=$ip;$row['lastSeen']=$now;$store['identities'][$key]=$row;loom_write_network_store($store);
  if($pdo=loom_moderation_db())try{
    $st=$pdo->prepare("INSERT INTO loom_identity_ips(owner_type,owner_id,ip_address,network_key,first_seen,last_seen,seen_count,last_project_slug,source) VALUES(?,?,?,?,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),1,?,?) ON DUPLICATE KEY UPDATE network_key=VALUES(network_key),last_seen=UTC_TIMESTAMP(3),seen_count=seen_count+1,last_project_slug=VALUES(last_project_slug),source=VALUES(source)");
    $st->execute([$ownerType,$ownerId,$ip,loom_ip_network_key($ip),$project?:null,$source]);
  }catch(Throwable $e){}
}
function loom_capture_request_ip(string $clientId,string $project=''): array {
  $clientId=safe_token($clientId);$obs=loom_request_ip_observation();if($clientId===''||!$obs['ip'])return $obs+['captured'=>false];
  $project=safe_slug($project);loom_record_identity_ip('client',$clientId,$obs['ip'],(string)$obs['source'],$project?:null);
  $auth=loom_auth_user();$linked=$auth?:loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');
  if($uid!=='')loom_record_identity_ip('user',$uid,$obs['ip'],(string)$obs['source'],$project?:null);
  return $obs+['captured'=>true,'userId'=>$uid?:null];
}
function loom_identity_ip_history(string $ownerType,string $ownerId): array {
  if(!in_array($ownerType,['client','user'],true)||$ownerId==='')return [];$rows=[];
  $store=loom_network_store();$local=$store['identities'][$ownerType.':'.$ownerId]['addresses']??[];
  foreach($local as $ip=>$r)$rows[$ip]=['ip'=>$ip,'displayIp'=>loom_ip_display($ip),'ipVersion'=>loom_ip_version($ip),'source'=>$r['source']??null,'networkKey'=>$r['networkKey']??loom_ip_network_key($ip),'networkLabel'=>loom_ip_network_label($ip),'geoHint'=>is_array($r['geoHint']??null)?$r['geoHint']:[],'firstSeen'=>$r['firstSeen']??null,'lastSeen'=>$r['lastSeen']??null,'seenCount'=>(int)($r['seenCount']??0),'lastProject'=>$r['lastProject']??null];
  if($pdo=loom_moderation_db())try{
    $st=$pdo->prepare("SELECT ip_address,network_key,first_seen,last_seen,seen_count,last_project_slug,source FROM loom_identity_ips WHERE owner_type=? AND owner_id=? ORDER BY last_seen DESC");$st->execute([$ownerType,$ownerId]);
    foreach($st->fetchAll() as $r){$ip=(string)$r['ip_address'];$cur=$rows[$ip]??[];$rows[$ip]=['ip'=>$ip,'displayIp'=>loom_ip_display($ip),'ipVersion'=>loom_ip_version($ip),'source'=>$r['source']??($cur['source']??null),'networkKey'=>$r['network_key']??($cur['networkKey']??loom_ip_network_key($ip)),'networkLabel'=>loom_ip_network_label($ip),'geoHint'=>$cur['geoHint']??[],'firstSeen'=>$r['first_seen']??($cur['firstSeen']??null),'lastSeen'=>$r['last_seen']??($cur['lastSeen']??null),'seenCount'=>max((int)($r['seen_count']??0),(int)($cur['seenCount']??0)),'lastProject'=>$r['last_project_slug']??($cur['lastProject']??null)];}
  }catch(Throwable $e){}
  $out=array_values($rows);usort($out,fn($a,$b)=>strcmp((string)($b['lastSeen']??''),(string)($a['lastSeen']??'')));return $out;
}
function loom_network_state_for_client(string $clientId,string $project=''): array {
  $obs=loom_capture_request_ip($clientId,$project);$auth=loom_auth_user();$linked=$auth?:loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');
  $type=$uid!==''?'user':'client';$id=$uid!==''?$uid:$clientId;$history=loom_identity_ip_history($type,$id);
  $preferred=$obs['ip']??null;$preferredVersion=loom_ip_version($preferred);$preferredSource=$obs['source']??null;
  // Prefer a known public IPv4 for human-friendly display when one has actually
  // been observed. Never synthesize/convert IPv6 into a fake IPv4 address.
  foreach($history as $row){$candidate=loom_valid_ip((string)($row['ip']??''));if($candidate&&loom_ip_version($candidate)==='IPv4'&&loom_ip_is_public($candidate)){$preferred=$candidate;$preferredVersion='IPv4';$preferredSource=$row['source']??$preferredSource;break;}}
  return ['currentIp'=>$obs['ip']??null,'currentIpDisplay'=>loom_ip_display($obs['ip']??null),'currentIpVersion'=>loom_ip_version($obs['ip']??null),'preferredIp'=>$preferred,'preferredIpDisplay'=>loom_ip_display($preferred),'preferredIpVersion'=>$preferredVersion,'source'=>$obs['source']??null,'preferredSource'=>$preferredSource,'history'=>$history,'knownIpCount'=>count($history),'ownerType'=>$type,'ownerId'=>$id,'note'=>'LOOM prefers a genuinely observed public IPv4 for display when available; otherwise it shows the full canonical IPv6. IP is network metadata only.'];
}

function loom_subject_key(string $type,string $id): string { return $type.':'.$id; }
function loom_subject_for_record(string $clientId='',string $userId=''): array {
  $userId=safe_token($userId);$clientId=safe_token($clientId);
  if($userId!=='')return ['type'=>'user','id'=>$userId];
  if($clientId!==''){$u=loom_account_user_for_client($clientId);$uid=(string)($u['user_id']??$u['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid];}
  return ['type'=>'client','id'=>$clientId];
}
function loom_subject_for_request(string $clientId=''): array {
  $auth=loom_auth_user();$uid=(string)($auth['user_id']??$auth['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid];
  return loom_subject_for_record($clientId,'');
}
function loom_project_subject_state(string $project,string $type,string $id): array {
  $project=safe_slug($project);$type=in_array($type,['client','user'],true)?$type:'client';$id=safe_token($id);
  $default=['project'=>$project,'subjectType'=>$type,'subjectId'=>$id,'banned'=>false,'includeData'=>false,'bannedAt'=>null,'bannedByUserId'=>null,'updatedAt'=>null];
  if($project===''||$id==='')return $default;
  $store=loom_moderation_store();$local=$store['projectStates'][$project][loom_subject_key($type,$id)]??null;if(is_array($local))$default=array_replace($default,$local);
  if($pdo=loom_moderation_db())try{
    $st=$pdo->prepare("SELECT * FROM loom_project_user_state WHERE project_slug=? AND subject_type=? AND subject_id=?");$st->execute([$project,$type,$id]);$r=$st->fetch();if($r)$default=array_replace($default,['banned'=>(bool)$r['banned'],'includeData'=>(bool)$r['include_data'],'bannedAt'=>$r['banned_at'],'bannedByUserId'=>$r['banned_by_user_id'],'updatedAt'=>$r['updated_at']]);
  }catch(Throwable $e){}
  return $default;
}
function loom_set_project_subject_state(string $project,string $type,string $id,bool $banned,bool $includeData,string $adminUserId=''): array {
  $project=safe_slug($project);$id=safe_token($id);if($project===''||!project_dir($project)||$id===''||!in_array($type,['client','user'],true))throw new RuntimeException('Invalid moderation target.');
  $now=server_timestamp();$existing=loom_project_subject_state($project,$type,$id);$row=['project'=>$project,'subjectType'=>$type,'subjectId'=>$id,'banned'=>$banned,'includeData'=>$includeData,'bannedAt'=>$banned?($existing['bannedAt']?:$now):null,'bannedByUserId'=>$banned?($adminUserId?:null):null,'updatedAt'=>$now];
  $store=loom_moderation_store();$store['projectStates'][$project][loom_subject_key($type,$id)]=$row;loom_write_moderation_store($store);
  if($pdo=loom_moderation_db())try{
    $st=$pdo->prepare("INSERT INTO loom_project_user_state(project_slug,subject_type,subject_id,banned,include_data,banned_at,banned_by_user_id,updated_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE banned=VALUES(banned),include_data=VALUES(include_data),banned_at=VALUES(banned_at),banned_by_user_id=VALUES(banned_by_user_id),updated_at=UTC_TIMESTAMP(3)");
    $st->execute([$project,$type,$id,$banned?1:0,$includeData?1:0,$banned?loom_db_dt($row['bannedAt']):null,$banned?($adminUserId?:null):null]);
  }catch(Throwable $e){}
  return $row;
}
function loom_project_access_status(string $project,string $clientId=''): array {
  $subject=loom_subject_for_request($clientId);$state=loom_project_subject_state($project,$subject['type'],$subject['id']);
  return ['allowed'=>!$state['banned'],'banned'=>(bool)$state['banned'],'subject'=>$subject,'state'=>$state];
}
function loom_enforce_project_access(string $project,string $clientId=''): void {
  $s=loom_project_access_status($project,$clientId);if(!$s['allowed'])json_out(['ok'=>false,'error'=>'project-access-banned','message'=>'This profile is banned from this project. The account remains preserved.','project'=>$project,'banned'=>true,'bannedAt'=>$s['state']['bannedAt']],403);
}
function loom_project_record_visible(string $project,string $clientId='',string $userId=''): bool {
  $subject=loom_subject_for_record($clientId,$userId);if($subject['id']==='')return true;$s=loom_project_subject_state($project,$subject['type'],$subject['id']);return !$s['banned']||$s['includeData'];
}
function loom_promote_client_moderation_to_user(string $clientId,string $userId): void {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return;$store=loom_moderation_store();
  foreach(($store['projectStates']??[]) as $project=>$states){$c=$states['client:'.$clientId]??null;if(!is_array($c))continue;$u=loom_project_subject_state($project,'user',$userId);$b=(bool)($c['banned']??false)||(bool)$u['banned'];$inc=(bool)($c['includeData']??false)||(bool)$u['includeData'];loom_set_project_subject_state($project,'user',$userId,$b,$inc,(string)($c['bannedByUserId']??''));}
  foreach(loom_identity_ip_history('client',$clientId) as $r)loom_record_identity_ip('user',$userId,(string)$r['ip'],(string)($r['source']??'migration'),(string)($r['lastProject']??''));
}
function loom_admin_audit(string $action,string $targetType='',string $targetId='',string $project='',array $payload=[]): void {
  $auth=loom_auth_user();$adminUserId=(string)($auth['user_id']??$auth['userId']??'');$adminClient=(string)($auth['auth_client_id']??'');$row=['id'=>event_id('audit'),'adminUserId'=>$adminUserId?:null,'adminClientId'=>$adminClient?:null,'action'=>$action,'targetType'=>$targetType?:null,'targetId'=>$targetId?:null,'project'=>$project?:null,'createdAt'=>server_timestamp(),'payload'=>$payload];
  $store=loom_moderation_store();$store['audit'][]=$row;if(count($store['audit'])>1000)$store['audit']=array_slice($store['audit'],-1000);loom_write_moderation_store($store);
  if($pdo=loom_moderation_db())try{$st=$pdo->prepare("INSERT INTO loom_admin_audit(audit_id,admin_user_id,admin_client_id,action_type,target_type,target_id,project_slug,created_at,payload) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(3),?)");$st->execute([$row['id'],$row['adminUserId'],$row['adminClientId'],$action,$row['targetType'],$row['targetId'],$row['project'],json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){}
}
function loom_migrate_network_and_moderation_to_db(): array {
  $pdo=loom_moderation_db();if(!$pdo)return ['ips'=>0,'projectStates'=>0,'audit'=>0];$counts=['ips'=>0,'projectStates'=>0,'audit'=>0];
  $n=loom_network_store();foreach(($n['identities']??[]) as $row){$type=(string)($row['ownerType']??'');$id=(string)($row['ownerId']??'');foreach(($row['addresses']??[]) as $ip=>$r){try{$st=$pdo->prepare("INSERT INTO loom_identity_ips(owner_type,owner_id,ip_address,network_key,first_seen,last_seen,seen_count,last_project_slug,source) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE first_seen=LEAST(first_seen,VALUES(first_seen)),last_seen=GREATEST(last_seen,VALUES(last_seen)),seen_count=GREATEST(seen_count,VALUES(seen_count)),last_project_slug=VALUES(last_project_slug),source=VALUES(source)");$st->execute([$type,$id,$ip,$r['networkKey']??loom_ip_network_key($ip),loom_db_dt($r['firstSeen']??server_timestamp()),loom_db_dt($r['lastSeen']??server_timestamp()),max(1,(int)($r['seenCount']??1)),$r['lastProject']??null,$r['source']??null]);$counts['ips']++;}catch(Throwable $e){}}}
  $m=loom_moderation_store();foreach(($m['projectStates']??[]) as $project=>$states)foreach($states as $r){try{$st=$pdo->prepare("INSERT INTO loom_project_user_state(project_slug,subject_type,subject_id,banned,include_data,banned_at,banned_by_user_id,updated_at) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE banned=VALUES(banned),include_data=VALUES(include_data),banned_at=VALUES(banned_at),banned_by_user_id=VALUES(banned_by_user_id),updated_at=VALUES(updated_at)");$st->execute([$project,$r['subjectType'],$r['subjectId'],!empty($r['banned'])?1:0,!empty($r['includeData'])?1:0,loom_db_dt($r['bannedAt']??null),$r['bannedByUserId']??null,loom_db_dt($r['updatedAt']??server_timestamp())]);$counts['projectStates']++;}catch(Throwable $e){}}
  return $counts;
}
