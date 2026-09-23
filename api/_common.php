<?php
// @loom-file release=0.15.09 revision=16 policy=package-priority
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function root_dir(): string { return realpath(__DIR__ . '/..') ?: dirname(__DIR__); }

/**
 * LOOM Clean Instance Protocol (0.12.13+)
 *
 * /instance is the ONLY mutable installation-owned filesystem boundary.
 * Release archives NEVER contain /instance. They contain /instance.sample only.
 *
 * There is intentionally no compatibility import from pre-Instance layouts.
 */
function loom_instance_ensure_dir(string $dir): string {
  if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir))
    throw new RuntimeException('LOOM Instance Vault could not be created: '.$dir);
  if(!is_writable($dir))
    throw new RuntimeException('LOOM Instance Vault is not writable: '.$dir);
  return $dir;
}
function loom_instance_root(): string {
  static $ready=null;
  if(is_string($ready)&&$ready!=='')return $ready;

  $dir=loom_instance_ensure_dir(root_dir().'/instance');

  $deny=$dir.'/.htaccess';
  if(!is_file($deny)){
    $ok=@file_put_contents($deny,"Require all denied\nOptions -Indexes\n",LOCK_EX);
    if($ok===false)throw new RuntimeException('LOOM could not protect the Instance Vault.');
  }

  $marker=$dir.'/.loom-instance.json';
  if(!is_file($marker)){
    $payload=[
      'schema'=>'loom-instance/v1',
      'protocol'=>'clean-instance',
      'createdAt'=>gmdate('c')
    ];
    $ok=@file_put_contents(
      $marker,
      json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",
      LOCK_EX
    );
    if($ok===false)throw new RuntimeException('LOOM could not initialize the Instance Vault.');
  }

  $ready=$dir;
  return $ready;
}
function loom_data_dir(): string {
  return loom_instance_ensure_dir(loom_instance_root().'/data');
}
function loom_config_dir(): string {
  return loom_instance_ensure_dir(loom_instance_root().'/config');
}
function loom_instance_project_dir(string $project): string {
  $slug=preg_replace('/[^a-z0-9_-]/','',strtolower($project))?:'';
  if($slug==='')throw new RuntimeException('Invalid project for Instance Vault.');
  return loom_instance_ensure_dir(loom_instance_root().'/projects/'.$slug);
}
function loom_project_override_file(string $project): string {
  return loom_instance_project_dir($project).'/project-overrides.json';
}
function loom_project_overlay_dir(string $project): string {
  return loom_instance_ensure_dir(loom_instance_project_dir($project).'/overlay');
}
function safe_slug(string $value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?: ''; }
function safe_token(string $value): string { return preg_replace('/[^a-zA-Z0-9_.-]/','',$value) ?: ''; }
function loom_instance_projects_root_path(): string { return loom_instance_root().'/projects'; }
function loom_instance_project_storage_path(string $project): string { $slug=safe_slug($project);return $slug!==''?loom_instance_projects_root_path().'/'.$slug:''; }
function loom_instance_project_runtime_path(string $project): string { $base=loom_instance_project_storage_path($project);return $base!==''?$base.'/project':''; }
function loom_instance_project_exists(string $project): bool { $dir=loom_instance_project_runtime_path($project);return $dir!==''&&is_file($dir.'/project.default.json'); }
function loom_project_source(string $project): ?string {
  $slug=safe_slug($project);if($slug==='')return null;
  if(loom_instance_project_exists($slug))return 'instance';
  $base=realpath(root_dir().'/projects');if(!$base)return null;$target=realpath($base.'/'.$slug);
  return ($target&&str_starts_with($target,$base.DIRECTORY_SEPARATOR)&&is_file($target.'/project.default.json'))?'release':null;
}
function loom_project_is_instance_owned(string $project): bool { return loom_project_source($project)==='instance'; }
function project_dir(string $project): ?string {
  $slug=safe_slug($project); if($slug==='')return null;
  $instance=loom_instance_project_runtime_path($slug);
  if($instance!==''&&is_file($instance.'/project.default.json')){ $real=realpath($instance); if($real)return $real; }
  $base=realpath(root_dir().'/projects'); if(!$base)return null;
  $target=realpath($base.'/'.$slug); if(!$target || !str_starts_with($target,$base.DIRECTORY_SEPARATOR)||!is_file($target.'/project.default.json'))return null;
  return $target;
}
function loom_all_project_slugs(): array {
  $slugs=[];$release=root_dir().'/projects';
  foreach(glob($release.'/*',GLOB_ONLYDIR)?:[] as $dir){$slug=safe_slug(basename($dir));if($slug!==''&&$slug[0]!=='_'&&is_file($dir.'/project.default.json'))$slugs[$slug]=true;}
  $instance=loom_instance_projects_root_path();if(is_dir($instance))foreach(scandir($instance)?:[] as $name){if($name==='.'||$name==='..')continue;$slug=safe_slug($name);if($slug!==''&&is_file($instance.'/'.$slug.'/project/project.default.json'))$slugs[$slug]=true;}
  $out=array_keys($slugs);sort($out,SORT_NATURAL|SORT_FLAG_CASE);return $out;
}
function loom_project_app_url(string $project): string {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)return '#';
  if(loom_project_is_instance_owned($slug)){
    $shell=root_dir().'/projects/_instance/app/index.html';$v=is_file($shell)?file_cache_version($shell):loom_release_version();
    return 'projects/_instance/app/?project='.rawurlencode($slug).'&v='.rawurlencode($v);
  }
  $app=$dir.'/app/index.html';$v=is_file($app)?file_cache_version($app):file_cache_version($dir.'/project.default.json');
  return 'projects/'.$slug.'/app/?project='.rawurlencode($slug).'&v='.rawurlencode($v);
}
function json_out(array $payload, int $status=200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
function web_base_path(): string {
  $script=(string)($_SERVER['SCRIPT_NAME']??'/api/index.php');
  $apiDir=str_replace('\\','/',dirname($script));
  $base=str_replace('\\','/',dirname($apiDir));
  return $base==='/' ? '' : rtrim($base,'/');
}
function rel_url(string $absolute): string {
  $root=root_dir();
  $relative=str_replace('\\','/',substr($absolute,strlen($root)));
  return web_base_path().$relative;
}

function file_cache_version(string $absolute): string {
  if(!is_file($absolute)) return 'missing';
  $hash=@hash_file('sha256',$absolute);
  if(is_string($hash)&&$hash!=='') return substr($hash,0,16);
  $mtime=@filemtime($absolute);$size=@filesize($absolute);
  return substr(hash('sha256',(string)$mtime.'|'.(string)$size),0,16);
}
function append_cache_version(string $url,string $version): string {
  return $url.((str_contains($url,'?'))?'&':'?').'v='.rawurlencode($version);
}
function versioned_rel_url(string $absolute): string {
  return append_cache_version(rel_url($absolute),file_cache_version($absolute));
}
function read_json_file(string $file): ?array { $x=json_decode((string)@file_get_contents($file),true); return is_array($x)?$x:null; }
function loom_release_version(string $fallback='0.12.07'): string { $m=read_json_file(root_dir().'/.loom-deployment.json'); $v=trim((string)($m['loom_release']??'')); return $v!==''?$v:$fallback; }
function server_epoch_ms(): int { return (int)round(microtime(true)*1000); }
function server_timestamp(): string {
  $dt=DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',microtime(true)),new DateTimeZone('UTC'));
  return $dt ? $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z') : gmdate('c');
}
function event_id(string $prefix='evt'): string { return $prefix.'_'.bin2hex(random_bytes(8)).'_'.base_convert((string)server_epoch_ms(),10,36); }
function log_dir(string $project): string { return loom_data_dir().'/logs/'.safe_slug($project); }
function presence_dir(string $project): string { return loom_data_dir().'/presence/'.safe_slug($project); }
function presence_file(string $project,string $session): string { return presence_dir($project).'/'.safe_token($session).'.json'; }
function append_project_event(string $project,array $event): array {
  $slug=safe_slug($project);
  if(function_exists('loom_project_record_visible')&&!loom_project_record_visible($slug,(string)($event['clientId']??''),(string)($event['userId']??''))){$event['project']=$slug;$event['droppedForModeration']=true;return $event;} $dir=log_dir($slug); if(!is_dir($dir))@mkdir($dir,0775,true);
  if(empty($event['id']))$event['id']=event_id();
  if(empty($event['serverTimestamp']))$event['serverTimestamp']=server_timestamp();
  if(empty($event['serverEpochMs']))$event['serverEpochMs']=server_epoch_ms();
  $event['project']=$slug;
  $file=$dir.'/'.gmdate('Y-m-d').'.jsonl';
  @file_put_contents($file,json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
  if(function_exists('loom_db_append_event'))loom_db_append_event($event);
  return $event;
}
function write_presence(string $project,string $session,array $payload): void {
  if(function_exists('loom_project_record_visible')&&!loom_project_record_visible($project,(string)($payload['clientId']??''),(string)($payload['userId']??'')))return;
  $dir=presence_dir($project); if(!is_dir($dir))@mkdir($dir,0775,true);
  @file_put_contents(presence_file($project,$session),json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_presence'))loom_db_write_presence($project,$session,$payload);
}
function action_snapshot(array $payload): array {
  $out=[];
  foreach(($payload['activeActions']??[]) as $a){
    if(!is_array($a))continue; $id=safe_token((string)($a['id']??'')); if($id==='')continue;
    $out[]=['id'=>$id,'name'=>(string)($a['name']??$id),'kind'=>(string)($a['kind']??'system'),'behavior'=>(string)($a['behavior']??'stateful')];
  }
  if(!$out){foreach(($payload['activeActionIds']??[]) as $id){$id=safe_token((string)$id);if($id!=='')$out[]=['id'=>$id,'name'=>$id,'kind'=>'system','behavior'=>'stateful'];}}
  $seen=[];return array_values(array_filter($out,function($a)use(&$seen){if(isset($seen[$a['id']]))return false;$seen[$a['id']]=true;return true;}));
}
function base_identity(array $p): array {
  return [
    'runtimeId'=>$p['runtimeId']??null,'clientId'=>$p['clientId']??null,'sessionId'=>$p['sessionId']??null,
    'userId'=>$p['userId']??null,'userLabel'=>$p['userLabel']??null
  ];
}
function emit_inactive_snapshot(string $project,array $presence,string $reason,bool $inferred): void {
  $base=base_identity($presence);
  foreach(action_snapshot($presence) as $a){
    append_project_event($project,$base+[
      'type'=>'action.state','actionId'=>$a['id'],'state'=>'inactive','name'=>$a['name'],'kind'=>$a['kind'],'behavior'=>$a['behavior'],
      'reason'=>$reason,'inferred'=>$inferred,'source'=>$inferred?'presence-reaper':'lifecycle-close'
    ]);
  }
}
function mark_stale_sessions(string $project): int {
  $slug=safe_slug($project); if($slug==='')return 0; $dir=presence_dir($slug); if(!is_dir($dir))return 0;
  $now=server_epoch_ms();$count=0;
  foreach(glob($dir.'/*.json')?:[] as $file){
    $p=read_json_file($file);if(!$p||($p['status']??'')!=='active')continue;
    $expires=(int)($p['leaseExpiresEpochMs']??0); if($expires<=0||$expires>$now)continue;
    $session=safe_token((string)($p['sessionId']??''));if($session==='')continue;
    $snapshot=action_snapshot($p);$ids=array_values(array_map(fn($a)=>$a['id'],$snapshot));$base=base_identity($p);
    $staleAt=server_timestamp();
    append_project_event($slug,$base+[
      'type'=>'session.stale','reason'=>'heartbeat-missed','inferred'=>true,
      'lastHeartbeatAt'=>$p['lastHeartbeatAt']??$p['serverTimestamp']??null,
      'staleAt'=>$staleAt,'heldActionIds'=>$ids,
      'note'=>'Heartbeat freshness lapsed. Session is retained and may resume.'
    ]);
    // IMPORTANT: stale is uncertainty, not termination. Preserve the last-known held-action snapshot.
    $p['status']='stale';$p['staleAt']=$staleAt;$p['staleEpochMs']=$now;$p['staleReason']='heartbeat-missed';
    $p['leaseExpiresEpochMs']=$expires;
    write_presence($slug,$session,$p);$count++;
  }
  return $count;
}

// Backward-compatible alias for older callers. v0.8.1 no longer expires/ends sessions on lease timeout.
function reap_expired_sessions(string $project): int { return mark_stale_sessions($project); }



// ---- LOOM privilege + administrator foundation (v0.11.12) ----
function loom_admin_dir(): string { $d=loom_data_dir().'/admin';if(!is_dir($d))@mkdir($d,0775,true);return $d; }
function loom_admin_identity_file(): string { return loom_admin_dir().'/identity.json'; }
function loom_admin_settings_dir(): string { return loom_admin_dir().'/settings'; }
function loom_admin_cookie_name(): string { return 'loom_admin_token'; }

function loom_admin_identity(): ?array {
  if(function_exists('loom_db_read_admin_state')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_admin_state();if(is_array($db)&&(!empty($db['clientId'])||!empty($db['userId'])))return $db;
  }
  $p=read_json_file(loom_admin_identity_file());
  return is_array($p)&&(!empty($p['clientId'])||!empty($p['userId']))?$p:null;
}
function loom_write_admin_identity(array $state): void {
  ensure_dir(loom_admin_dir());
  @file_put_contents(loom_admin_identity_file(),json_encode($state,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_admin_state')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_admin_state($state);
}
function loom_set_admin_cookie(string $token): void {
  $secure=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');
  setcookie(loom_admin_cookie_name(),$token,[
    'expires'=>time()+60*60*24*365*5,
    'path'=>'/',
    'secure'=>$secure,
    'httponly'=>true,
    'samesite'=>'Strict'
  ]);
}
function loom_admin_cookie_token(): string {
  return safe_token((string)($_COOKIE[loom_admin_cookie_name()]??''));
}
function loom_admin_cookie_valid(): bool {
  $state=loom_admin_identity(); if(!$state||empty($state['tokenHash']))return false;
  $token=loom_admin_cookie_token(); if($token==='')return false;
  return password_verify($token,(string)$state['tokenHash']);
}
function loom_client_is_admin(string $clientId): bool {
  $clientId=safe_token($clientId);
  $auth=function_exists('loom_auth_user')?loom_auth_user():null;
  $authId=(string)($auth['user_id']??$auth['userId']??'');
  $authPrivilege=(string)($auth['privilege']??'User');

  // v0.11.13: a valid authenticated permanent account whose durable account
  // record says Admin is itself sufficient proof of Admin access. This avoids
  // making authorization depend on a second denormalized admin-state mapping.
  if($authId!=='' && strcasecmp($authPrivilege,'Admin')===0)return true;

  $state=loom_admin_identity();if(!$state)return false;
  $uid=(string)($state['userId']??'');
  $stateClient=(string)($state['clientId']??'');

  // Backward-compatible linked-account path.
  if($uid!=='' && $authId!=='' && hash_equals($uid,$authId))return true;

  // Preserve the original bootstrap browser as a recovery path. The secret
  // HttpOnly Admin credential is still required. If another permanent user is
  // actively authenticated on this browser, do not let the bootstrap cookie
  // elevate that different account.
  if($stateClient!=='' && $clientId!=='' && hash_equals($stateClient,$clientId) && loom_admin_cookie_valid()){
    if($uid==='' || $authId==='' || hash_equals($uid,$authId))return true;
  }
  return false;
}
function loom_bootstrap_or_privilege(string $clientId,bool $allowBootstrap=false): array {
  $clientId=safe_token($clientId);
  if($clientId===''||!str_starts_with($clientId,'client_'))return ['privilege'=>'User','isAdmin'=>false,'bootstrapped'=>false,'bootstrapAvailable'=>loom_admin_identity()===null];
  $state=loom_admin_identity();
  if(!$state && $allowBootstrap){
    ensure_dir(loom_admin_dir());
    $lock=@fopen(loom_admin_dir().'/bootstrap.lock','c+');
    if($lock)@flock($lock,LOCK_EX);
    $state=loom_admin_identity(); // re-check while holding the bootstrap lock
    if(!$state){
      $token='adm_'.bin2hex(random_bytes(32));
      $auth=function_exists('loom_auth_user')?loom_auth_user():null;
      $authId=(string)($auth['user_id']??$auth['userId']??'');
      $state=[
        'schemaVersion'=>'1.0',
        'clientId'=>$clientId,
        'userId'=>$authId!==''?$authId:null,
        'tokenHash'=>password_hash($token,PASSWORD_DEFAULT),
        'createdAt'=>server_timestamp(),
        'createdEpochMs'=>server_epoch_ms(),
        'bootstrapMethod'=>'explicit-first-admin'
      ];
      loom_write_admin_identity($state);
      loom_set_admin_cookie($token);
      if($authId!==''&&function_exists('loom_link_admin_to_user_if_applicable'))loom_link_admin_to_user_if_applicable($clientId,$authId);
      if($lock){@flock($lock,LOCK_UN);@fclose($lock);}
      return ['privilege'=>'Admin','isAdmin'=>true,'bootstrapped'=>true,'bootstrapAvailable'=>false,'linkedUserId'=>$authId!==''?$authId:null];
    }
    if($lock){@flock($lock,LOCK_UN);@fclose($lock);}
  }
  $admin=loom_client_is_admin($clientId);
  return [
    'privilege'=>$admin?'Admin':'User',
    'isAdmin'=>$admin,
    'bootstrapped'=>false,
    'adminIdentityMatch'=>$state?hash_equals((string)$state['clientId'],$clientId):false,
    'credentialPresent'=>loom_admin_cookie_token()!=='',
    'bootstrapAvailable'=>$state===null
  ];
}
function loom_require_admin(string $clientId): void {
  if(!loom_client_is_admin(safe_token($clientId))) json_out(['ok'=>false,'error'=>'admin-access-required','privilege'=>'User'],403);
}
function loom_admin_settings_file(string $project): string {
  ensure_dir(loom_admin_settings_dir());
  return loom_admin_settings_dir().'/'.safe_slug($project).'.json';
}
function loom_read_admin_settings(string $project): array {
  if(function_exists('loom_db_read_module_settings')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_module_settings(safe_slug($project));if(is_array($db))return $db;
  }
  return read_json_file(loom_admin_settings_file($project))?:['schemaVersion'=>'1.0','project'=>safe_slug($project),'modules'=>[]];
}
function loom_write_admin_settings(string $project,array $settings): void {
  ensure_dir(loom_admin_settings_dir());
  $settings['schemaVersion']='1.0';$settings['project']=safe_slug($project);$settings['updatedAt']=server_timestamp();
  @file_put_contents(loom_admin_settings_file($project),json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_module_settings')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_module_settings(safe_slug($project),$settings);
}
function loom_project_module_enabled(string $project,string $actionId,bool $default=true): bool {
  $s=loom_read_admin_settings($project);$row=$s['moduleStates'][$actionId]??null;
  return is_array($row)&&array_key_exists('enabled',$row)?(bool)$row['enabled']:$default;
}
function loom_set_project_module_enabled(string $project,string $actionId,bool $enabled): void {
  $s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['enabled']=$enabled;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);
}
function loom_project_module_hide_on_mobile(string $project,string $actionId,bool $default=false): bool {
  $s=loom_read_admin_settings($project);$row=$s['moduleStates'][$actionId]??null;
  return is_array($row)&&array_key_exists('hideOnMobile',$row)?(bool)$row['hideOnMobile']:$default;
}
function loom_set_project_module_hide_on_mobile(string $project,string $actionId,bool $hide): void {
  $s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['hideOnMobile']=$hide;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);
}
function loom_global_module_enabled(string $actionId,bool $default=true): bool {
  $s=loom_read_global_settings();$row=$s['moduleStates'][$actionId]??null;return is_array($row)&&array_key_exists('enabled',$row)?(bool)$row['enabled']:$default;
}
function loom_set_global_module_enabled(string $actionId,bool $enabled): void {
  $s=loom_read_global_settings();if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['enabled']=$enabled;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_global_settings($s);
}
function loom_global_settings_file(): string {
  ensure_dir(loom_admin_dir());
  return loom_admin_dir().'/global-settings.json';
}
function loom_read_global_settings(): array {
  if(function_exists('loom_db_read_global_settings')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_global_settings();if(is_array($db))return $db;
  }
  return read_json_file(loom_global_settings_file())?:['schemaVersion'=>'1.0','modules'=>[]];
}
function loom_write_global_settings(array $settings): void {
  ensure_dir(loom_admin_dir());
  $settings['schemaVersion']='1.0';$settings['updatedAt']=server_timestamp();
  @file_put_contents(loom_global_settings_file(),json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_global_settings')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_global_settings($settings);
}
// ---- LOOM Domain Landing / project-at-installation-root (v0.15.08) ----
function loom_domain_routing_file(): string {
  return loom_config_dir().'/domain-routing.json';
}
function loom_domain_routing_defaults(): array {
  return [
    'schemaVersion'=>'1.0',
    'homePath'=>'home',
    'landing'=>['mode'=>'loom-home','project'=>''],
    'hostBindings'=>[]
  ];
}
function loom_read_domain_routing(): array {
  $defaults=loom_domain_routing_defaults();
  $saved=read_json_file(loom_domain_routing_file());
  if(!is_array($saved))return $defaults;
  $mode=(string)($saved['landing']['mode']??'loom-home');
  if(!in_array($mode,['loom-home','project'],true))$mode='loom-home';
  $project=safe_slug((string)($saved['landing']['project']??''));
  $homePath=safe_slug((string)($saved['homePath']??'home'))?:'home';
  if($homePath!=='home')$homePath='home'; // reserved stable recovery route in v1
  return [
    'schemaVersion'=>'1.0',
    'homePath'=>$homePath,
    'landing'=>['mode'=>$mode,'project'=>$project],
    'hostBindings'=>is_array($saved['hostBindings']??null)?$saved['hostBindings']:[],
    'updatedAt'=>$saved['updatedAt']??null
  ];
}
function loom_write_domain_routing(string $mode,string $project=''): array {
  $mode=in_array($mode,['loom-home','project'],true)?$mode:'loom-home';
  $project=safe_slug($project);
  if($mode==='project'){
    if($project===''||!project_dir($project))throw new RuntimeException('Choose an active LOOM project for the base URL.');
  }else{$project='';}
  $current=loom_read_domain_routing();
  $next=[
    'schemaVersion'=>'1.0',
    'homePath'=>'home',
    'landing'=>['mode'=>$mode,'project'=>$project],
    'hostBindings'=>is_array($current['hostBindings']??null)?$current['hostBindings']:[],
    'updatedAt'=>server_timestamp()
  ];
  $file=loom_domain_routing_file();
  $json=json_encode($next,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
  if(@file_put_contents($file,$json,LOCK_EX)===false)throw new RuntimeException('LOOM could not save Domain Landing configuration.');
  return $next;
}
function loom_domain_available_projects(): array {
  $out=[];
  foreach(loom_all_project_slugs() as $slug){
    $dir=project_dir($slug);if(!$dir)continue;
    $data=loom_project_effective_data($slug);
    $out[]=[
      'slug'=>$slug,
      'name'=>(string)($data['name']??humanize_project_slug($slug)),
      'source'=>loom_project_source($slug)??'unknown'
    ];
  }
  usort($out,fn($a,$b)=>strcasecmp((string)$a['name'],(string)$b['name'])?:strcmp((string)$a['slug'],(string)$b['slug']));
  return $out;
}
function loom_domain_routing_effective(): array {
  $saved=loom_read_domain_routing();
  $moduleEnabled=loom_global_module_enabled('loom.domain-landing',true);
  if(!$moduleEnabled)return ['mode'=>'loom-home','project'=>'','reason'=>'module-disabled','saved'=>$saved];
  $mode=(string)($saved['landing']['mode']??'loom-home');
  $project=safe_slug((string)($saved['landing']['project']??''));
  if($mode==='project'){
    if($project!==''&&project_dir($project))return ['mode'=>'project','project'=>$project,'reason'=>null,'saved'=>$saved];
    return ['mode'=>'loom-home','project'=>'','reason'=>'project-unavailable','saved'=>$saved];
  }
  return ['mode'=>'loom-home','project'=>'','reason'=>null,'saved'=>$saved];
}
function loom_domain_routing_payload(): array {
  $effective=loom_domain_routing_effective();
  $saved=$effective['saved'];
  return [
    'schemaVersion'=>'1.0',
    'saved'=>[
      'mode'=>(string)($saved['landing']['mode']??'loom-home'),
      'project'=>safe_slug((string)($saved['landing']['project']??'')),
      'updatedAt'=>$saved['updatedAt']??null
    ],
    'effective'=>[
      'mode'=>$effective['mode'],
      'project'=>$effective['project'],
      'reason'=>$effective['reason']
    ],
    'homePath'=>'home',
    'availableProjects'=>loom_domain_available_projects()
  ];
}
function loom_project_is_domain_landing(string $project): bool {
  $slug=safe_slug($project);$effective=loom_domain_routing_effective();
  return $slug!==''&&$effective['mode']==='project'&&$effective['project']===$slug;
}
function loom_project_public_url(string $project): string {
  $slug=safe_slug($project);if($slug==='')return '#';
  if(loom_project_is_domain_landing($slug)){
    $base=web_base_path();return ($base===''?'/':$base.'/');
  }
  return loom_project_app_url($slug);
}
function loom_global_core_modules_dir(): string { return root_dir().'/core-modules'; }
function loom_core_module_records(?string $scope=null): array {
  $root=loom_global_core_modules_dir();$out=[];if(!is_dir($root))return $out;
  foreach(glob($root.'/*/manifest.json')?:[] as $file){
    $m=read_json_file($file);if(!$m)continue;
    $moduleScope=(string)($m['module']['scope']??'global');if($scope!==null&&$moduleScope!==$scope)continue;
    $id=(string)($m['action']['id']??'');if($id==='')continue;
    $out[]=['manifest'=>$m,'manifestFile'=>$file,'folder'=>dirname($file),'scope'=>$moduleScope,'manifestEnabled'=>(bool)($m['enabled']??true)];
  }
  usort($out,fn($a,$b)=>strcmp((string)($a['manifest']['module']['order']??'50000'),(string)($b['manifest']['module']['order']??'50000'))?:strcmp((string)($a['manifest']['action']['id']??''),(string)($b['manifest']['action']['id']??'')));
  return $out;
}
function loom_scan_global_core_modules(): array {
  $out=[];
  foreach(loom_core_module_records('global') as $record){$m=$record['manifest'];$id=(string)$m['action']['id'];$out[]=['actionId'=>$id,'name'=>(string)($m['action']['name']??$id),'description'=>(string)($m['action']['description']??''),'order'=>(string)($m['module']['order']??'50000'),'admin_settings'=>is_array($m['admin_settings']??null)?$m['admin_settings']:['fields'=>[]],'defaults'=>is_array($m['config']??null)?$m['config']:[],'manifestEnabled'=>(bool)($m['enabled']??true)];}
  return $out;
}
function loom_scan_project_core_modules(): array {
  $out=[];
  foreach(loom_core_module_records('project') as $record){$m=$record['manifest'];$id=(string)$m['action']['id'];$out[]=['actionId'=>$id,'name'=>(string)($m['action']['name']??$id),'description'=>(string)($m['action']['description']??''),'order'=>(string)($m['module']['order']??'50000'),'admin_settings'=>is_array($m['admin_settings']??null)?$m['admin_settings']:['fields'=>[]],'presentation'=>is_array($m['presentation']??null)?$m['presentation']:[],'defaults'=>is_array($m['config']??null)?$m['config']:[],'source'=>'core-project','manifestEnabled'=>(bool)($m['enabled']??true),'manifestHideOnMobile'=>(bool)($m['presentation']['responsive']['hideOnMobile']??false)];}
  return $out;
}
function loom_global_settings_payload(): array {
  $saved=loom_read_global_settings();$mods=loom_scan_global_core_modules();$effective=[];$states=[];
  foreach($mods as &$m){$id=$m['actionId'];$override=$saved['modules'][$id]??[];$m['overrides']=is_array($override)?$override:[];$m['values']=array_replace_recursive($m['defaults'],$m['overrides']);$m['enabled']=loom_global_module_enabled($id,(bool)($m['manifestEnabled']??true));$states[$id]=['enabled'=>$m['enabled']];$effective[$id]=$m['values'];}unset($m);
  return ['modules'=>$mods,'settings'=>$effective,'moduleStates'=>$states,'domainRouting'=>loom_domain_routing_payload(),'updatedAt'=>$saved['updatedAt']??null];
}

function loom_module_admin_overrides(string $project,string $actionId): array {
  $settings=loom_read_admin_settings($project);$override=$settings['modules'][$actionId]??[];
  return is_array($override)?$override:[];
}
function loom_module_config_with_admin_overrides(string $project,array $manifest): array {
  $base=is_array($manifest['config']??null)?$manifest['config']:[];
  $aid=(string)($manifest['action']['id']??'');
  if($aid==='')return $base;
  $settings=loom_read_admin_settings($project);
  $override=$settings['modules'][$aid]??[];
  return is_array($override)?array_replace_recursive($base,$override):$base;
}

// ---- LOOM project identity / profile management (v0.12.02) ----
function loom_clean_project_text(mixed $value,int $max=240): string {
  $s=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',(string)$value)??'');
  return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);
}
function loom_project_base_file(string $project): ?string {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)return null;
  $file=$dir.'/project.default.json';
  return is_file($file)?$file:null;
}
function loom_project_base_data(string $project): array {
  $file=loom_project_base_file($project);return $file?(read_json_file($file)?:[]):[];
}
function loom_project_override_data(string $project): array {
  $x=read_json_file(loom_project_override_file($project));return is_array($x)?$x:[];
}
function loom_project_effective_data(string $project): array {
  $base=loom_project_base_data($project);
  $override=loom_project_override_data($project);
  return array_replace_recursive($base,$override);
}
function loom_project_overlay_asset(string $project,string $relative): ?string {
  $relative=ltrim(str_replace('\\','/',$relative),'/');
  if($relative===''||str_contains($relative,'..'))return null;
  $base=loom_project_overlay_dir($project);$candidate=$base.'/'.$relative;
  ensure_dir(dirname($candidate));
  $parent=realpath(dirname($candidate));$realBase=realpath($base);
  if(!$parent||!$realBase||!str_starts_with($parent,$realBase))return null;
  return $candidate;
}
function loom_project_asset_url(string $project,string $relative): ?string {
  $overlay=loom_project_overlay_asset($project,$relative);
  if($overlay&&is_file($overlay)){
    $v=file_cache_version($overlay);
    return web_base_path().'/api/project-asset.php?project='.rawurlencode(safe_slug($project)).'&path='.rawurlencode($relative).'&v='.rawurlencode($v);
  }
  $dir=project_dir($project);if(!$dir)return null;
  $candidate=realpath($dir.'/'.ltrim(str_replace('\\','/',$relative),'/'));
  $prefix=rtrim((string)realpath($dir),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
  return ($candidate&&is_file($candidate)&&str_starts_with($candidate,$prefix))?versioned_rel_url($candidate):null;
}
function loom_project_profile_payload(string $project): ?array {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)return null;
  $data=loom_project_effective_data($slug);$branding=is_array($data['branding']??null)?$data['branding']:[];
  $asset=ltrim(str_replace('\\','/',(string)($branding['logo_asset']??'assets/logo.png')),'/');
  $logoUrl=$asset!==''&&!str_contains($asset,'..')?loom_project_asset_url($slug,$asset):null;
  return [
    'slug'=>$slug,
    'name'=>(string)($data['name']??humanize_project_slug($slug)),
    'tagline'=>(string)($data['tagline']??''),
    'description'=>(string)($data['description']??''),
    'bio'=>(string)($data['bio']??''),
    'theme'=>(string)($data['theme']??'default'),
    'version'=>(string)($data['version']??'0.1.0'),
    'engine'=>(string)($data['engine']??'LOOM'),
    'social_color'=>preg_match('/^#[0-9A-Fa-f]{6}$/',(string)($data['social_color']??''))?strtoupper((string)$data['social_color']):'#000000',
    'branding'=>[
      'logo_asset'=>$asset?:'assets/logo.png',
      'logo_alt'=>(string)($branding['logo_alt']??($data['name']??humanize_project_slug($slug))),
      'logo_url'=>$logoUrl
    ]
  ];
}
function loom_write_project_profile(string $project,array $incoming): array {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)throw new RuntimeException('Project not found');
  $data=loom_project_override_data($slug);
  if(array_key_exists('name',$incoming)){$name=loom_clean_project_text($incoming['name'],80);if($name==='')throw new RuntimeException('Project name is required');$data['name']=$name;}
  if(array_key_exists('tagline',$incoming))$data['tagline']=loom_clean_project_text($incoming['tagline'],140);
  if(array_key_exists('description',$incoming))$data['description']=loom_clean_project_text($incoming['description'],500);
  if(array_key_exists('bio',$incoming))$data['bio']=loom_clean_project_text($incoming['bio'],1800);
  if(array_key_exists('social_color',$incoming)){$c=strtoupper(trim((string)$incoming['social_color']));if(!preg_match('/^#[0-9A-F]{6}$/',$c))throw new RuntimeException('Project social color must be a 6-digit hex color');$data['social_color']=$c;}
  if(array_key_exists('theme',$incoming)){$theme=preg_replace('/[^a-zA-Z0-9_.-]/','',loom_clean_project_text($incoming['theme'],60));$data['theme']=$theme!==''?$theme:'default';}
  $effective=loom_project_effective_data($slug);
  $branding=is_array($data['branding']??null)?$data['branding']:[];
  $baseBrand=is_array($effective['branding']??null)?$effective['branding']:[];
  $branding['logo_asset']=(string)($branding['logo_asset']??$baseBrand['logo_asset']??'assets/logo.png');
  if(array_key_exists('logo_alt',$incoming))$branding['logo_alt']=loom_clean_project_text($incoming['logo_alt'],120);
  elseif(empty($branding['logo_alt']))$branding['logo_alt']=(string)($data['name']??$effective['name']??humanize_project_slug($slug));
  $data['branding']=$branding;$data['updated_at']=server_timestamp();
  $file=loom_project_override_file($slug);ensure_dir(dirname($file));
  $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||@file_put_contents($file,$json."\n",LOCK_EX)===false)throw new RuntimeException('Could not save project profile overrides');
  return loom_project_profile_payload($slug)?:[];
}
function loom_decode_png_payload(string $payload): string {
  if(str_contains($payload,','))$payload=substr($payload,strpos($payload,',')+1);
  $png=base64_decode($payload,true);if($png===false||strlen($png)<16||substr($png,0,8)!=="\x89PNG\r\n\x1a\n")throw new RuntimeException('Expected PNG image payload');
  if(strlen($png)>8*1024*1024)throw new RuntimeException('Project logo must be 8 MB or smaller');
  return $png;
}
function loom_save_project_logo(string $project,string $pngPayload): array {
  $slug=safe_slug($project);if(!project_dir($slug))throw new RuntimeException('Project not found');
  $png=loom_decode_png_payload($pngPayload);$target=loom_project_overlay_asset($slug,'assets/logo.png');
  if(!$target)throw new RuntimeException('Could not resolve persistent project logo path');
  ensure_dir(dirname($target));
  if(@file_put_contents($target,$png,LOCK_EX)===false)throw new RuntimeException('Could not store persistent project logo');
  $data=loom_project_override_data($slug);$effective=loom_project_effective_data($slug);
  $branding=is_array($data['branding']??null)?$data['branding']:[];
  $branding['logo_asset']='assets/logo.png';
  $branding['logo_alt']=(string)($branding['logo_alt']??($effective['name']??humanize_project_slug($slug)));
  $data['branding']=$branding;$data['updated_at']=server_timestamp();
  $file=loom_project_override_file($slug);ensure_dir(dirname($file));
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  return loom_project_profile_payload($slug)?:[];
}

// ---- LOOM project archive lifecycle (v0.9.1) ----
function archives_root(): string { $d=loom_instance_root().'/archives/projects';ensure_dir($d);return $d; }
function archive_project_dir(string $archiveSlug): string { return archives_root().'/'.safe_slug($archiveSlug); }
function ensure_dir(string $dir): void { if(!is_dir($dir)) @mkdir($dir,0775,true); }
function recursive_copy(string $src,string $dst): bool {
  if(!is_dir($src)) return false; ensure_dir($dst);
  $items=scandir($src); if($items===false)return false;
  foreach($items as $item){ if($item==='.'||$item==='..')continue; $s=$src.'/'.$item;$d=$dst.'/'.$item;
    if(is_dir($s)){ if(!recursive_copy($s,$d))return false; }
    else { ensure_dir(dirname($d)); if(!@copy($s,$d))return false; }
  }
  return true;
}
function recursive_remove(string $path): void {
  if(!file_exists($path))return;
  if(is_file($path)||is_link($path)){@unlink($path);return;}
  foreach(scandir($path)?:[] as $item){if($item==='.'||$item==='..')continue;recursive_remove($path.'/'.$item);} @rmdir($path);
}
function move_tree(string $src,string $dst): bool {
  if(!file_exists($src))return false; ensure_dir(dirname($dst));
  if(@rename($src,$dst))return true;
  if(is_dir($src) && recursive_copy($src,$dst)){recursive_remove($src);return true;}
  return false;
}
function project_exists_anywhere(string $slug, ?string $ignoreArchive=null): bool {
  $slug=safe_slug($slug); if($slug==='')return true;
  if(is_dir(root_dir().'/projects/'.$slug)||loom_instance_project_exists($slug))return true;
  $a=archive_project_dir($slug); if(is_dir($a) && $slug!==safe_slug((string)$ignoreArchive))return true;
  return false;
}
function project_base_slug(string $slug): string {
  $slug=safe_slug($slug); $base=preg_replace('/-old-\d+$/','',$slug); return $base!==''?$base:$slug;
}
function next_old_slug(string $slug, ?string $ignoreArchive=null): string {
  $base=project_base_slug($slug); $max=0;
  foreach([root_dir().'/projects',loom_instance_projects_root_path(),archives_root()] as $dir){
    if(!is_dir($dir))continue;
    foreach(scandir($dir)?:[] as $name){
      if($name==='.'||$name==='..'||$name===safe_slug((string)$ignoreArchive))continue;
      if(preg_match('/^'.preg_quote($base,'/').'-old-(\d+)$/',$name,$m))$max=max($max,(int)$m[1]);
    }
  }
  return $base.'-old-'.($max+1);
}
function humanize_project_slug(string $slug): string {
  $s=str_replace(['-','_'],' ',safe_slug($slug)); return ucwords($s);
}
function write_archive_meta(string $archiveSlug,array $meta): void {
  $dir=archive_project_dir($archiveSlug); ensure_dir($dir);
  @file_put_contents($dir.'/archive.json',json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function archive_active_project(string $slug,string $reason='user-archive'): array {
  $slug=safe_slug($slug);if($slug===''||!project_dir($slug))return ['ok'=>false,'error'=>'active-project-not-found'];
  $projectData=loom_project_effective_data($slug);$source=loom_project_source($slug);$archiveSlug=next_old_slug($slug);$archiveDir=archive_project_dir($archiveSlug);ensure_dir($archiveDir);
  if($source==='instance'){
    $src=loom_instance_project_storage_path($slug);$dst=$archiveDir.'/instance-project';
    if(file_exists($dst)||!move_tree($src,$dst))return ['ok'=>false,'error'=>'archive-move-failed'];
  }else{
    $src=root_dir().'/projects/'.$slug;$dst=$archiveDir.'/project';
    if(file_exists($dst)||!move_tree($src,$dst))return ['ok'=>false,'error'=>'archive-move-failed'];
    foreach(['logs','presence'] as $kind){$sourcePath=loom_data_dir().'/'.$kind.'/'.$slug;if(is_dir($sourcePath))move_tree($sourcePath,$archiveDir.'/data/'.$kind);}
  }
  $meta=['archive_slug'=>$archiveSlug,'original_slug'=>$slug,'original_name'=>$projectData['name']??humanize_project_slug($slug),'archived_name'=>humanize_project_slug($archiveSlug),'reason'=>$reason,'archived_at'=>server_timestamp(),'archived_epoch_ms'=>server_epoch_ms(),'project_version'=>$projectData['version']??null,'source_type'=>$source?:'release'];
  write_archive_meta($archiveSlug,$meta);return ['ok'=>true,'archive_slug'=>$archiveSlug,'original_slug'=>$slug,'meta'=>$meta];
}
function rewrite_project_identity(string $projectDir,string $slug): void {
  $file=$projectDir.'/project.default.json';
  if(!is_file($file))throw new RuntimeException('Archived project is missing project.default.json');
  $data=read_json_file($file)?:[];$data['slug']=$slug;
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  $override=loom_project_override_data($slug);$override['name']=humanize_project_slug($slug);$override['restored_from_archive']=$override['restored_from_archive']??null;
  @file_put_contents(loom_project_override_file($slug),json_encode($override,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
}
function restore_archived_project(string $archiveSlug): array {
  $archiveSlug=safe_slug($archiveSlug);$archiveDir=archive_project_dir($archiveSlug);if($archiveSlug===''||!is_dir($archiveDir))return ['ok'=>false,'error'=>'archive-not-found'];
  $meta=read_json_file($archiveDir.'/archive.json')?:[];$isInstance=is_dir($archiveDir.'/instance-project');$desired=$archiveSlug;$target=$desired;if(project_exists_anywhere($target,$archiveSlug))$target=next_old_slug($desired,$archiveSlug);
  if($isInstance){
    $src=$archiveDir.'/instance-project';$dst=loom_instance_projects_root_path().'/'.$target;if(!move_tree($src,$dst))return ['ok'=>false,'error'=>'restore-move-failed'];
    $runtime=$dst.'/project';rewrite_project_identity($runtime,$target);$override=read_json_file($dst.'/project-overrides.json')?:[];$override['restored_from_archive']=$archiveSlug;$override['restored_at']=server_timestamp();@file_put_contents($dst.'/project-overrides.json',json_encode($override,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  }else{
    $src=$archiveDir.'/project';if(!is_dir($src))return ['ok'=>false,'error'=>'archive-not-found'];$dst=root_dir().'/projects/'.$target;if(!move_tree($src,$dst))return ['ok'=>false,'error'=>'restore-move-failed'];rewrite_project_identity($dst,$target);$pd=loom_project_override_data($target);$pd['restored_from_archive']=$archiveSlug;$pd['restored_at']=server_timestamp();@file_put_contents(loom_project_override_file($target),json_encode($pd,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);foreach(['logs','presence'] as $kind){$sp=$archiveDir.'/data/'.$kind;if(is_dir($sp))move_tree($sp,loom_data_dir().'/'.$kind.'/'.$target);}
  }
  recursive_remove($archiveDir);return ['ok'=>true,'archive_slug'=>$archiveSlug,'restored_slug'=>$target,'meta'=>$meta];
}
function list_archived_projects(): array {
  $out=[];$base=archives_root();if(!is_dir($base))return $out;
  foreach(scandir($base)?:[] as $slug){if($slug==='.'||$slug==='..')continue;$dir=$base.'/'.$slug;if(!is_dir($dir))continue;$meta=read_json_file($dir.'/archive.json')?:[];$pf=is_file($dir.'/instance-project/project/project.default.json')?$dir.'/instance-project/project/project.default.json':$dir.'/project/project.default.json';$pd=read_json_file($pf)?:[];$out[]=['slug'=>$slug,'name'=>$meta['archived_name']??humanize_project_slug($slug),'description'=>$pd['description']??'Archived LOOM project','version'=>$pd['version']??($meta['project_version']??'0.0.0'),'archived_at'=>$meta['archived_at']??null,'original_slug'=>$meta['original_slug']??null,'reason'=>$meta['reason']??null,'source_type'=>$meta['source_type']??(is_dir($dir.'/instance-project')?'instance':'release')];}
  usort($out,fn($a,$b)=>strcmp((string)($b['archived_at']??''),(string)($a['archived_at']??'')));return $out;
}
function install_project_template(string $templateSlug,string $targetSlug): bool {
  $slug=safe_slug($targetSlug);$src=root_dir().'/templates/projects/'.safe_slug($templateSlug);$storage=loom_instance_projects_root_path().'/'.$slug;$dst=$storage.'/project';
  if($slug===''||!is_dir($src)||project_exists_anywhere($slug))return false;
  if(!is_dir(loom_instance_projects_root_path())&&!@mkdir(loom_instance_projects_root_path(),0775,true))return false;
  if(!recursive_copy($src,$dst)){recursive_remove($storage);return false;}
  $marker=['schema'=>'loom-instance-project/v1','slug'=>$slug,'createdAt'=>server_timestamp(),'runtime'=>'project','releaseManaged'=>false];
  @file_put_contents($storage.'/.loom-instance-project.json',json_encode($marker,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  return true;
}
function green_beans_active_action_ids(): array {
  $root=root_dir().'/projects/green-beans/actions';$ids=[];
  if(!is_dir($root))return $ids;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
  foreach($it as $file){
    if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
    $m=read_json_file($file->getPathname());$id=(string)($m['action']['id']??'');if($id!=='')$ids[]=$id;
  }
  sort($ids);return array_values(array_unique($ids));
}
// Database and account helpers are loaded after the shared filesystem/lifecycle primitives.
require_once __DIR__.'/_database.php';
require_once __DIR__.'/_accounts.php';
require_once __DIR__.'/_global_profiles.php';
require_once __DIR__.'/_project_identities.php';
require_once __DIR__.'/_project_state.php';
require_once __DIR__.'/_avatars.php';
require_once __DIR__.'/_moderation.php';
require_once __DIR__.'/_guest_identities.php';
require_once __DIR__.'/_integrity.php';
require_once __DIR__.'/_guest_profiles.php';
require_once __DIR__.'/_migrations.php';
require_once __DIR__.'/_capabilities.php';
require_once __DIR__.'/_sandbox.php';
require_once __DIR__.'/_audit.php';
loom_migration_bootstrap();
