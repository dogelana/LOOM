<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
declare(strict_types=1);

function loom_db_config_file(): string { return loom_config_dir().'/database.json'; }
function loom_db_config(): array {
  $d=read_json_file(loom_db_config_file())?:[];
  return [
    'enabled'=>(bool)($d['enabled']??false),
    'host'=>(string)($d['host']??''),
    'port'=>(int)($d['port']??3306),
    'database'=>(string)($d['database']??''),
    'username'=>(string)($d['username']??''),
    'password'=>(string)($d['password']??''),
    'charset'=>'utf8mb4',
    'updatedAt'=>$d['updatedAt']??null
  ];
}
function loom_db_public_config(): array {
  $c=loom_db_config();
  return [
    'enabled'=>$c['enabled'],'host'=>$c['host'],'port'=>$c['port'],'database'=>$c['database'],
    'username'=>$c['username'],'hasPassword'=>$c['password']!=='','updatedAt'=>$c['updatedAt']
  ];
}
function loom_db_make_pdo(array $c): PDO {
  if($c['host']===''||$c['database']===''||$c['username']==='')throw new RuntimeException('Database host, database name, and username are required.');
  $dsn='mysql:host='.$c['host'].';port='.(int)($c['port']?:3306).';dbname='.$c['database'].';charset=utf8mb4';
  return new PDO($dsn,$c['username'],$c['password'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_TIMEOUT=>5
  ]);
}
function loom_db_pdo(bool $requireInitialized=false): ?PDO {
  $c=loom_db_config(); if(!$c['enabled'])return null;
  static $cache=[];$key=hash('sha256',json_encode($c));
  if(!array_key_exists($key,$cache)){
    try{$cache[$key]=loom_db_make_pdo($c);}catch(Throwable $e){$cache[$key]=null;}
  }
  $pdo=$cache[$key];
  if(!$pdo)return null;
  if($requireInitialized && !loom_db_is_initialized($pdo))return null;
  return $pdo;
}
function loom_db_is_initialized(?PDO $pdo=null): bool {
  $pdo=$pdo?:loom_db_pdo(false);if(!$pdo)return false;
  try{$s=$pdo->query("SHOW TABLES LIKE 'loom_meta'");return (bool)$s->fetchColumn();}catch(Throwable $e){return false;}
}
function loom_db_ready(): bool { return loom_db_pdo(true) instanceof PDO; }
function loom_db_status(): array {
  $c=loom_db_public_config();$out=$c+['configured'=>$c['enabled'],'connected'=>false,'initialized'=>false,'driver'=>'mysql','error'=>null];
  if(!$c['enabled'])return $out;
  try{$pdo=loom_db_make_pdo(loom_db_config());$out['connected']=true;$out['initialized']=loom_db_is_initialized($pdo);$out['serverVersion']=$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);}
  catch(Throwable $e){$out['error']=$e->getMessage();}
  return $out;
}
function loom_db_save_config(array $incoming): array {
  ensure_dir(dirname(loom_db_config_file()));$old=loom_db_config();
  $c=[
    'enabled'=>true,
    'host'=>trim((string)($incoming['host']??$old['host'])),
    'port'=>max(1,min(65535,(int)($incoming['port']??$old['port']??3306))),
    'database'=>trim((string)($incoming['database']??$old['database'])),
    'username'=>trim((string)($incoming['username']??$old['username'])),
    'password'=>(string)($incoming['password']??'')
  ];
  if($c['password']===''&&$old['password']!=='')$c['password']=$old['password'];
  $pdo=loom_db_make_pdo($c); // validate before persisting
  $c['updatedAt']=server_timestamp();
  @file_put_contents(loom_db_config_file(),json_encode($c,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
  return loom_db_status();
}
function loom_db_initialize(): array {
  $pdo=loom_db_pdo(false);if(!$pdo)throw new RuntimeException('Database is not connected.');
  $sql=(string)@file_get_contents(root_dir().'/database/mysql-schema.sql');
  if(trim($sql)==='')throw new RuntimeException('Database schema file is missing.');
  $chunks=preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[];
  foreach($chunks as $chunk){$chunk=trim($chunk);if($chunk===''||str_starts_with($chunk,'--')){ 
      // Preserve statements that begin after leading comments.
      $chunk=preg_replace('/^(?:--[^\n]*\n\s*)+/','',$chunk)??$chunk;
      $chunk=trim($chunk);
    }
    if($chunk!=='')$pdo->exec($chunk);
  }
  $st=$pdo->prepare("INSERT INTO loom_meta(meta_key,value_text) VALUES('schema_version','0.12.00') ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)");
  $st->execute();
  return loom_db_status();
}
function loom_db_dt(?string $iso): ?string {
  if(!$iso)return null;$ts=strtotime($iso);if($ts===false)return null;return gmdate('Y-m-d H:i:s.000',$ts);
}
function loom_db_append_event(array $event): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{
    $st=$pdo->prepare("INSERT IGNORE INTO loom_events(event_id,session_id,client_id,user_id,project_slug,action_id,event_type,state,inferred,client_timestamp,server_timestamp,payload) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([
      (string)($event['id']??event_id()),$event['sessionId']??null,$event['clientId']??null,$event['userId']??null,
      (string)($event['project']??''),$event['actionId']??null,(string)($event['type']??'event'),$event['state']??null,
      !empty($event['inferred'])?1:0,loom_db_dt($event['clientTimestamp']??null),loom_db_dt($event['serverTimestamp']??server_timestamp()),
      json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ]);
    if(!empty($event['clientId'])){
      $now=loom_db_dt($event['serverTimestamp']??server_timestamp())?:gmdate('Y-m-d H:i:s.000');
      $c=$pdo->prepare("INSERT INTO loom_clients(client_id,user_id,user_label,first_seen,last_seen,metadata) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=COALESCE(VALUES(user_id),user_id),user_label=COALESCE(VALUES(user_label),user_label),last_seen=VALUES(last_seen)");
      $c->execute([$event['clientId'],$event['userId']??null,$event['userLabel']??null,$now,$now,null]);
    }
  }catch(Throwable $e){}
}
function loom_db_write_presence(string $project,string $session,array $p): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{
    $last=loom_db_dt($p['lastHeartbeatAt']??$p['serverTimestamp']??server_timestamp())?:gmdate('Y-m-d H:i:s.000');
    $started=loom_db_dt($p['startedAt']??$p['serverTimestamp']??null);
    $leaseMs=(int)($p['leaseExpiresEpochMs']??0);$lease=$leaseMs>0?gmdate('Y-m-d H:i:s.000',(int)floor($leaseMs/1000)):null;
    $status=(string)($p['status']??'historical');if($status==='active')$status='live';if(!in_array($status,['live','stale','closed','expired','historical'],true))$status='historical';
    $st=$pdo->prepare("INSERT INTO loom_sessions(session_id,client_id,user_id,user_label,project_slug,runtime_id,started_at,ended_at,last_seen,lease_expires_at,status,end_reason,active_actions,metadata) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),user_label=VALUES(user_label),runtime_id=VALUES(runtime_id),last_seen=VALUES(last_seen),lease_expires_at=VALUES(lease_expires_at),status=VALUES(status),end_reason=VALUES(end_reason),active_actions=VALUES(active_actions),metadata=VALUES(metadata)");
    $st->execute([
      $session,$p['clientId']??'', $p['userId']??null,$p['userLabel']??null,$project,$p['runtimeId']??null,$started,
      loom_db_dt($p['endedAt']??null),$last,$lease,$status,$p['closedReason']??$p['endReason']??null,
      json_encode($p['activeActions']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
      json_encode($p['meta']??null,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ]);
  }catch(Throwable $e){}
}
function loom_db_read_module_settings(string $project): ?array {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;
  try{
    $st=$pdo->prepare("SELECT action_id,config_json,updated_at FROM loom_module_settings WHERE project_slug=?");$st->execute([$project]);
    $out=['schemaVersion'=>'1.0','project'=>$project,'modules'=>[],'updatedAt'=>null];
    foreach($st->fetchAll() as $r){$cfg=json_decode((string)$r['config_json'],true);$out['modules'][$r['action_id']]=is_array($cfg)?$cfg:[];$out['updatedAt']=max((string)($out['updatedAt']??''),(string)$r['updated_at']);}
    return $out;
  }catch(Throwable $e){return null;}
}
function loom_db_write_module_settings(string $project,array $settings): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{
    $pdo->beginTransaction();$del=$pdo->prepare("DELETE FROM loom_module_settings WHERE project_slug=?");$del->execute([$project]);
    $ins=$pdo->prepare("INSERT INTO loom_module_settings(project_slug,action_id,config_json,updated_at) VALUES(?,?,?,UTC_TIMESTAMP(3))");
    foreach(($settings['modules']??[]) as $id=>$cfg)$ins->execute([$project,$id,json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();}
}
function loom_db_read_global_settings(): ?array {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;
  try{$rows=$pdo->query("SELECT action_id,config_json,updated_at FROM loom_global_settings")->fetchAll();$out=['schemaVersion'=>'1.0','modules'=>[],'updatedAt'=>null];foreach($rows as $r){$cfg=json_decode((string)$r['config_json'],true);$out['modules'][$r['action_id']]=is_array($cfg)?$cfg:[];$out['updatedAt']=max((string)($out['updatedAt']??''),(string)$r['updated_at']);}return $out;}catch(Throwable $e){return null;}
}
function loom_db_write_global_settings(array $settings): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{$pdo->beginTransaction();$pdo->exec("DELETE FROM loom_global_settings");$ins=$pdo->prepare("INSERT INTO loom_global_settings(action_id,config_json,updated_at) VALUES(?,?,UTC_TIMESTAMP(3))");foreach(($settings['modules']??[]) as $id=>$cfg)$ins->execute([$id,json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();}
}

function loom_db_read_client_profile(string $clientId): ?array {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;
  try{$st=$pdo->prepare("SELECT * FROM loom_client_profiles WHERE client_id=?");$st->execute([$clientId]);$r=$st->fetch();if(!$r)return null;$payload=json_decode((string)($r['payload_json']??''),true);
    $p=is_array($payload)?$payload:[];
    $p['profileId']=$p['profileId']??$r['profile_id'];$p['clientId']=$p['clientId']??$clientId;$p['username']=array_key_exists('username',$p)?$p['username']:$r['username'];
    if(empty($p['createdAt']))$p['createdAt']=$r['created_at'];if(empty($p['updatedAt']))$p['updatedAt']=$r['updated_at'];
    return $p;
  }catch(Throwable $e){return null;}
}
function loom_db_write_client_profile(array $profile): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{$st=$pdo->prepare("INSERT INTO loom_client_profiles(client_id,profile_id,username,username_norm,created_at,updated_at,payload_json) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE profile_id=VALUES(profile_id),username=VALUES(username),username_norm=VALUES(username_norm),updated_at=VALUES(updated_at),payload_json=VALUES(payload_json)");
    $st->execute([$profile['clientId'],$profile['profileId'],$profile['username']??null,isset($profile['username'])&&$profile['username']!==null?loom_username_norm((string)$profile['username']):null,loom_db_dt($profile['createdAt']??null),loom_db_dt($profile['updatedAt']??null),json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  }catch(Throwable $e){throw $e;}
}
function loom_db_read_admin_state(): ?array {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;
  try{$r=$pdo->query("SELECT * FROM loom_admin_state WHERE state_id=1")->fetch();if(!$r)return null;return ['clientId'=>$r['client_id'],'userId'=>$r['user_id'],'tokenHash'=>$r['token_hash'],'createdAt'=>$r['created_at'],'bootstrapMethod'=>$r['bootstrap_method']];}catch(Throwable $e){return null;}
}
function loom_db_write_admin_state(array $state): void {
  $pdo=loom_db_pdo(true);if(!$pdo)return;
  try{$st=$pdo->prepare("INSERT INTO loom_admin_state(state_id,client_id,user_id,token_hash,created_at,bootstrap_method) VALUES(1,?,?,?,?,?) ON DUPLICATE KEY UPDATE client_id=VALUES(client_id),user_id=VALUES(user_id),token_hash=VALUES(token_hash),bootstrap_method=VALUES(bootstrap_method)");
    $st->execute([$state['clientId']??null,$state['userId']??null,$state['tokenHash']??null,loom_db_dt($state['createdAt']??server_timestamp()),$state['bootstrapMethod']??null]);}catch(Throwable $e){}
}
function loom_db_migrate_local(): array {
  $pdo=loom_db_pdo(true);if(!$pdo)throw new RuntimeException('Initialize the database schema first.');
  $counts=['profiles'=>0,'accounts'=>0,'globalSettings'=>0,'globalProfiles'=>0,'projectIdentities'=>0,'projectModuleStates'=>0,'guestIdentities'=>0,'avatars'=>0,'settings'=>0,'events'=>0,'sessions'=>0,'admin'=>0,'ips'=>0,'projectStates'=>0,'audit'=>0];

  // Client profiles.
  foreach(glob(loom_data_dir().'/users/*.json')?:[] as $file){
    $p=read_json_file($file);if(!$p||empty($p['clientId']))continue;
    try{
      loom_db_write_client_profile($p);
      $counts['profiles']++;
    }catch(Throwable $e){}
  }

  // Temporary account store.
  if(function_exists('loom_temp_account_store')){
    $store=loom_temp_account_store();
    foreach(($store['users']??[]) as $u){
      try{
        $st=$pdo->prepare("INSERT INTO loom_users(user_id,username,username_norm,email,email_norm,password_hash,privilege,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username),username_norm=VALUES(username_norm),email=VALUES(email),email_norm=VALUES(email_norm),password_hash=VALUES(password_hash),privilege=VALUES(privilege),updated_at=VALUES(updated_at)");
        $st->execute([$u['userId'],$u['username'],$u['usernameNorm'],$u['email'],$u['emailNorm'],$u['passwordHash'],$u['privilege']??'User',loom_db_dt($u['createdAt']),loom_db_dt($u['updatedAt'])]);$counts['accounts']++;
      }catch(Throwable $e){}
    }
    foreach(($store['clients']??[]) as $client=>$userId){
      try{$st=$pdo->prepare("INSERT INTO loom_user_clients(client_id,user_id,linked_at) VALUES(?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)");$st->execute([$client,$userId]);}catch(Throwable $e){}
    }
    foreach(($store['sessions']??[]) as $hash=>$sess){
      try{
        $st=$pdo->prepare("INSERT IGNORE INTO loom_auth_sessions(token_hash,user_id,client_id,created_at,expires_at,last_seen) VALUES(?,?,?,?,?,?)");
        $st->execute([$hash,$sess['userId'],$sess['clientId'],loom_db_dt($sess['createdAt']??server_timestamp()),loom_db_dt($sess['expiresAt']??gmdate('c',time()+2592000)),loom_db_dt($sess['createdAt']??server_timestamp())]);
      }catch(Throwable $e){}
    }
    foreach(($store['usernames']??[]) as $norm=>$r){
      try{$st=$pdo->prepare("INSERT INTO loom_username_registry(username_norm,username,owner_type,owner_id,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE username=VALUES(username),owner_type=VALUES(owner_type),owner_id=VALUES(owner_id),updated_at=VALUES(updated_at)");$st->execute([$norm,$r['username'],$r['ownerType'],$r['ownerId']]);}catch(Throwable $e){}
    }
  }

  // Global LOOM profiles + project identity overrides.
  if(function_exists('loom_global_profile_migrate_temp_to_db'))$counts['globalProfiles']+=loom_global_profile_migrate_temp_to_db();
  if(function_exists('loom_project_identity_migrate_temp_to_db'))$counts['projectIdentities']+=loom_project_identity_migrate_temp_to_db();
  if(function_exists('loom_project_state_migrate_temp_to_db'))$counts['projectModuleStates']+=loom_project_state_migrate_temp_to_db();
  if(function_exists('loom_guest_migrate_to_db'))$counts['guestIdentities']+=loom_guest_migrate_to_db();

  // Module settings.
  foreach(glob(loom_data_dir().'/admin/settings/*.json')?:[] as $file){
    $project=basename($file,'.json');$s=read_json_file($file);if(!$s)continue;loom_db_write_module_settings($project,$s);$counts['settings']++;
  }

  // Global LOOM core settings.
  $gs=read_json_file(loom_data_dir().'/admin/global-settings.json');if($gs){loom_db_write_global_settings($gs);$counts['globalSettings']=1;}

  // Admin identity.
  $ai=read_json_file(loom_data_dir().'/admin/identity.json');if($ai){loom_db_write_admin_state($ai);$counts['admin']=1;}

  // Telemetry.
  foreach(glob(loom_data_dir().'/logs/*/*.jsonl')?:[] as $file){
    foreach(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$e=json_decode($line,true);if(!is_array($e))continue;loom_db_append_event($e);$counts['events']++;}
  }
  foreach(glob(loom_data_dir().'/presence/*/*.json')?:[] as $file){
    $p=read_json_file($file);if(!$p)continue;$project=basename(dirname($file));$session=(string)($p['sessionId']??basename($file,'.json'));loom_db_write_presence($project,$session,$p);$counts['sessions']++;
  }

  if(function_exists('loom_migrate_network_and_moderation_to_db')){$extra=loom_migrate_network_and_moderation_to_db();foreach($extra as $k=>$v)$counts[$k]=($counts[$k]??0)+(int)$v;}

  $st=$pdo->prepare("INSERT INTO loom_meta(meta_key,value_text) VALUES('local_migration_at',?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)");$st->execute([server_timestamp()]);
  return $counts;
}
