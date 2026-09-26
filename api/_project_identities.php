<?php
// @loom-file release=0.15.67 revision=10 policy=package-priority
declare(strict_types=1);

// LOOM Project Identity Standard v0.15.67
// A project username has one authoritative mode:
//   global  -> dynamically resolve the CURRENT LOOM-wide visible username.
//   project -> use an explicitly saved project override.
// A copied historical LOOM username is never considered a custom override merely
// because an older LOOM build persisted the inherited value into the project row.
function loom_project_identity_file(): string { ensure_dir(loom_data_dir().'/users'); return loom_data_dir().'/users/project-identities.json'; }
function loom_project_identity_store(): array { return array_replace(['schemaVersion'=>'1.2','identities'=>[]],read_json_file(loom_project_identity_file())?:[]); }
function loom_write_project_identity_store(array $s): void { ensure_dir(dirname(loom_project_identity_file()));$s['schemaVersion']='1.2';$s['updatedAt']=server_timestamp();@file_put_contents(loom_project_identity_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function loom_project_identity_key(string $project,string $type,string $id): string { return safe_slug($project).'|'.$type.'|'.safe_token($id); }
function loom_project_identity_id(string $project,string $type,string $id): string { return 'pident_'.substr(hash('sha256',loom_project_identity_key($project,$type,$id)),0,20); }
function loom_project_identity_db_table(): void {
  if(!loom_db_ready())return;static $done=false;if($done)return;
  try{
    $pdo=loom_db_pdo(true);
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_project_identities (project_slug VARCHAR(96) NOT NULL,owner_type ENUM('client','user') NOT NULL,owner_id VARCHAR(96) NOT NULL,identity_id VARCHAR(96) NOT NULL,username_mode VARCHAR(16) NOT NULL DEFAULT 'global',username_explicit TINYINT(1) NULL DEFAULT NULL,username VARCHAR(64) NULL,username_norm VARCHAR(64) NULL,avatar_mode VARCHAR(32) NOT NULL DEFAULT 'auto',custom_ext VARCHAR(12) NULL,mime_type VARCHAR(64) NULL,byte_size INT UNSIGNED NULL,created_at DATETIME(3) NOT NULL,updated_at DATETIME(3) NOT NULL,PRIMARY KEY(project_slug,owner_type,owner_id),UNIQUE KEY uq_project_identity_username(project_slug,username_norm),UNIQUE KEY uq_project_identity_id(identity_id),INDEX idx_project_identity_owner(owner_type,owner_id),INDEX idx_project_identity_project(project_slug,updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try{$pdo->exec("ALTER TABLE loom_project_identities ADD COLUMN username_mode VARCHAR(16) NOT NULL DEFAULT 'global' AFTER identity_id");}catch(Throwable $e){}
    try{$pdo->exec("ALTER TABLE loom_project_identities ADD COLUMN username_explicit TINYINT(1) NULL DEFAULT NULL AFTER username_mode");}catch(Throwable $e){}
    $done=true;
  }catch(Throwable $e){}
}
function loom_project_identity_owner_for_client(string $clientId): array { $clientId=safe_token($clientId);$linked=loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid,'user'=>$linked];$auth=loom_auth_user();$uid=(string)($auth['user_id']??$auth['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid,'user'=>$auth];$id=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($clientId):$clientId;return ['type'=>'client','id'=>$id,'user'=>null]; }
function loom_project_identity_assert_mutation_access(string $clientId): void { loom_global_profile_assert_mutation_access($clientId); }
function loom_project_identity_row_normalize(array $r): array {
  $username=$r['username']??null;
  $mode=(string)($r['username_mode']??$r['usernameMode']??'');
  if(!in_array($mode,['global','project'],true))$mode=($username!==null&&trim((string)$username)!=='')?'project':'global';
  $explicitRaw=$r['username_explicit']??$r['usernameExplicit']??null;
  $explicit=$explicitRaw===null?false:(bool)$explicitRaw;
  $explicitKnown=$explicitRaw!==null;
  $avatar=(string)($r['avatar_mode']??$r['avatarMode']??'auto');if($avatar==='loom-default')$avatar='global';if(!in_array($avatar,['auto','global','project-default','custom'],true))$avatar='auto';
  return ['project'=>(string)($r['project_slug']??$r['project']??''),'ownerType'=>(string)($r['owner_type']??$r['ownerType']??'client'),'ownerId'=>(string)($r['owner_id']??$r['ownerId']??''),'identityId'=>(string)($r['identity_id']??$r['identityId']??''),'usernameMode'=>$mode,'usernameExplicit'=>$explicit,'usernameExplicitKnown'=>$explicitKnown,'username'=>$username,'usernameNorm'=>$r['username_norm']??$r['usernameNorm']??null,'avatarMode'=>$avatar,'customExt'=>$r['custom_ext']??$r['customExt']??null,'mimeType'=>$r['mime_type']??$r['mimeType']??null,'byteSize'=>isset($r['byte_size'])?(int)$r['byte_size']:($r['byteSize']??null),'createdAt'=>$r['created_at']??$r['createdAt']??null,'updatedAt'=>$r['updated_at']??$r['updatedAt']??null];
}
function loom_project_identity_get_for_owner(string $project,string $type,string $id): ?array { $project=safe_slug($project);$id=safe_token($id);if($project===''||$id===''||!in_array($type,['client','user'],true))return null;loom_project_identity_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_project_identities WHERE project_slug=? AND owner_type=? AND owner_id=?");$st->execute([$project,$type,$id]);$r=$st->fetch();if($r)return loom_project_identity_row_normalize($r);}catch(Throwable $e){}$s=loom_project_identity_store();$r=$s['identities'][loom_project_identity_key($project,$type,$id)]??null;return is_array($r)?loom_project_identity_row_normalize($r):null; }
function loom_project_identity_rows_for_project(string $project): array { $project=safe_slug($project);$out=[];loom_project_identity_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_project_identities WHERE project_slug=?");$st->execute([$project]);foreach($st->fetchAll() as $r){$n=loom_project_identity_row_normalize($r);$out[loom_project_identity_key($project,$n['ownerType'],$n['ownerId'])]=$n;}}catch(Throwable $e){}foreach((loom_project_identity_store()['identities']??[]) as $r){$n=loom_project_identity_row_normalize($r);if($n['project']!==$project)continue;$k=loom_project_identity_key($project,$n['ownerType'],$n['ownerId']);if(!isset($out[$k]))$out[$k]=$n;}return array_values($out); }
function loom_project_identity_effective_username_from_row(array $row,?string $globalCandidate=null): ?string { $r=loom_project_identity_row_normalize($row);if($r['usernameMode']==='project'&&($r['usernameExplicit']||!$r['usernameExplicitKnown'])&&trim((string)($r['username']??''))!=='')return (string)$r['username'];if($globalCandidate!==null)return $globalCandidate;$g=loom_global_profile_ensure_for_owner($r['ownerType'],$r['ownerId']);$visible=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($g):loom_clean_username((string)($g['displayName']??$g['username']??''));return $visible!==''?$visible:null; }
function loom_project_identity_effective_username(string $project,string $type,string $id): ?string { $r=loom_project_identity_get_for_owner($project,$type,$id);if(!$r){$g=loom_global_profile_ensure_for_owner($type,$id);$visible=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($g):loom_clean_username((string)($g['displayName']??$g['username']??''));return $visible!==''?$visible:null;}return loom_project_identity_effective_username_from_row($r); }
function loom_project_identity_nonmutating_global_name(string $type,string $id): ?string {
  // Conflict checks must never call the profile *ensure* path. Doing so while a
  // global profile is itself being repaired creates a cycle:
  // global-profile write -> project conflict check -> global-profile ensure -> ...
  // Read the currently stored presentation value only; ordinary profile reads
  // will repair legacy/internal names separately.
  $p=loom_global_profile_get($type,$id);if($p){$visible=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($p):loom_clean_username((string)($p['displayName']??$p['username']??''));if($visible!=='')return $visible;}
  return null;
}
function loom_project_identity_conflict(string $project,string $type,string $id,string $candidate): ?array {
  $norm=loom_username_norm($candidate);if($norm==='')return null;
  foreach(loom_project_identity_rows_for_project($project) as $r){
    if($r['ownerType']===$type&&$r['ownerId']===$id)continue;
    if(($r['usernameMode']??'global')==='project'&&(($r['usernameExplicit']??false)||!($r['usernameExplicitKnown']??false))&&trim((string)($r['username']??''))!=='')$effective=(string)$r['username'];
    else $effective=loom_project_identity_nonmutating_global_name((string)$r['ownerType'],(string)$r['ownerId']);
    if($effective!==null&&loom_username_norm($effective)===$norm)return $r;
  }
  return null;
}
function loom_project_identity_raw_rows_for_owner(string $type,string $id): array {
  $id=safe_token($id);if($id===''||!in_array($type,['client','user'],true))return [];$rows=[];
  loom_project_identity_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_project_identities WHERE owner_type=? AND owner_id=?");$st->execute([$type,$id]);foreach($st->fetchAll() as $raw){$r=loom_project_identity_row_normalize($raw);$rows[$r['project']]=$r;}}catch(Throwable $e){}
  foreach((loom_project_identity_store()['identities']??[]) as $raw){$r=loom_project_identity_row_normalize($raw);if($r['ownerType']===$type&&$r['ownerId']===$id&&!isset($rows[$r['project']]))$rows[$r['project']]=$r;}
  return array_values($rows);
}
function loom_project_identity_global_username_conflicts(string $type,string $id,string $candidate): bool {
  // Intentionally use raw owner rows here. The enriched list function resolves
  // inherited usernames by calling loom_global_profile_ensure_for_owner(), which
  // is unsafe from inside a global-profile write and caused the 0.15.66 request
  // recursion / PHP worker exhaustion incident.
  foreach(loom_project_identity_raw_rows_for_owner($type,$id) as $mine){if(($mine['usernameMode']??'global')!=='global')continue;if(loom_project_identity_conflict((string)$mine['project'],$type,$id,$candidate))return true;}
  return false;
}
function loom_project_identity_write(array $row): array {
  $r=loom_project_identity_row_normalize($row);$project=safe_slug($r['project']);$type=$r['ownerType'];$id=safe_token($r['ownerId']);if($project===''||!project_dir($project)||!in_array($type,['client','user'],true)||$id==='')throw new RuntimeException('Invalid project identity.');
  $r['project']=$project;$r['ownerId']=$id;$r['identityId']=$r['identityId']?:loom_project_identity_id($project,$type,$id);$r['usernameMode']=in_array($r['usernameMode'],['global','project'],true)?$r['usernameMode']:'global';$r['avatarMode']=in_array($r['avatarMode'],['auto','global','project-default','custom'],true)?$r['avatarMode']:'auto';$r['createdAt']=$r['createdAt']?:server_timestamp();$r['updatedAt']=$r['updatedAt']?:server_timestamp();
  if($r['usernameMode']==='global'){$r['usernameExplicit']=false;$r['username']=null;$r['usernameNorm']=null;}else{$u=$r['username']!==null?loom_clean_username((string)$r['username']):null;$r['username']=$u!==''?$u:null;if($r['username']===null)throw new RuntimeException('Project username cannot be empty.');$r['usernameExplicit']=true;$r['usernameNorm']=loom_username_norm($r['username']);}
  $r['usernameExplicitKnown']=true;$effective=loom_project_identity_effective_username_from_row($r);if($effective&&loom_project_identity_conflict($project,$type,$id,$effective))throw new RuntimeException('That username is already in use in this project.');
  $stored=$r;unset($stored['usernameExplicitKnown']);$s=loom_project_identity_store();$s['identities'][loom_project_identity_key($project,$type,$id)]=$stored;loom_write_project_identity_store($s);
  loom_project_identity_db_table();if(loom_db_ready()){$pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_project_identities(project_slug,owner_type,owner_id,identity_id,username_mode,username_explicit,username,username_norm,avatar_mode,custom_ext,mime_type,byte_size,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE identity_id=VALUES(identity_id),username_mode=VALUES(username_mode),username_explicit=VALUES(username_explicit),username=VALUES(username),username_norm=VALUES(username_norm),avatar_mode=VALUES(avatar_mode),custom_ext=VALUES(custom_ext),mime_type=VALUES(mime_type),byte_size=VALUES(byte_size),updated_at=VALUES(updated_at)");$st->execute([$project,$type,$id,$r['identityId'],$r['usernameMode'],$r['usernameExplicit']?1:0,$r['username'],$r['usernameNorm'],$r['avatarMode'],$r['customExt'],$r['mimeType'],$r['byteSize'],loom_db_dt($r['createdAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($r['updatedAt'])?:gmdate('Y-m-d H:i:s.000')]);}
  return $r+['effectiveUsername'=>$effective];
}
function loom_project_identity_client_ids_for_owner(array $owner,string $currentClientId=''): array {
  $ids=[];$add=function($v)use(&$ids){$v=safe_token((string)$v);if($v!==''&&str_starts_with($v,'client_'))$ids[$v]=true;};$add($currentClientId);
  if(($owner['type']??'')==='client')$add($owner['id']??'');
  if(($owner['type']??'')==='user'){
    $uid=safe_token((string)($owner['id']??''));$tmp=loom_temp_account_store();foreach(($tmp['clients']??[]) as $cid=>$mapped)if(safe_token((string)$mapped)===$uid)$add($cid);
    if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT client_id FROM loom_user_clients WHERE user_id=?");$st->execute([$uid]);foreach($st->fetchAll() as $x)$add($x['client_id']??'');}catch(Throwable $e){}
    if(function_exists('loom_guest_list_for_user'))foreach(loom_guest_list_for_user($uid) as $g)$add($g['primaryClientId']??'');
  }
  return array_keys($ids);
}
function loom_project_identity_aliases_for_owner(string $clientId,array $owner): array {
  $aliases=[];$add=function($v)use(&$aliases){$v=loom_clean_username((string)$v);if($v!=='')$aliases[loom_username_norm($v)]=$v;};
  try{$gp=loom_global_profile_ensure_for_owner((string)$owner['type'],(string)$owner['id']);$add($gp['username']??'');$add($gp['displayName']??'');if(function_exists('loom_global_profile_display_name'))$add(loom_global_profile_display_name($gp));}catch(Throwable $e){}
  $u=$owner['user']??null;if(is_array($u)){$add($u['username']??$u['user_name']??'');$add($u['displayName']??$u['display_name']??'');}
  foreach(loom_project_identity_client_ids_for_owner($owner,$clientId) as $cid){
    $p=read_json_file(loom_data_dir().'/users/'.hash('sha256',$cid).'.json')?:[];if(loom_db_ready()&&function_exists('loom_db_read_client_profile')){$dbp=loom_db_read_client_profile($cid);if(is_array($dbp))$p=array_replace($p,$dbp);}foreach(['username','displayName','display_name','_userLabel','userLabel'] as $k)$add($p[$k]??'');
    if(function_exists('loom_guest_profile_for_client')){$guest=loom_guest_profile_for_client($cid);if($guest&&function_exists('loom_guest_profile_public')){$pub=loom_guest_profile_public($guest);$add($pub['displayName']??'');}}
    if(loom_db_ready())try{$st=loom_db_pdo(true)->prepare("SELECT user_label FROM loom_clients WHERE client_id=?");$st->execute([$cid]);$x=$st->fetch();if($x)$add($x['user_label']??'');}catch(Throwable $e){}
  }
  return $aliases;
}
function loom_project_identity_legacy_log_alias_match(string $project,array $owner,string $clientId,string $name): bool {
  $target=loom_username_norm($name);if($target==='')return false;$clients=array_fill_keys(loom_project_identity_client_ids_for_owner($owner,$clientId),true);if(!$clients)return false;$dir=log_dir($project);if(!is_dir($dir))return false;
  foreach(glob($dir.'/*.jsonl')?:[] as $f){$fh=@fopen($f,'rb');if(!$fh)continue;while(($line=fgets($fh))!==false){$e=json_decode($line,true);if(!is_array($e))continue;$cid=safe_token((string)($e['clientId']??''));if($cid===''||!isset($clients[$cid]))continue;foreach(['userLabel','username','displayName'] as $k){$v=loom_clean_username((string)($e[$k]??''));if($v!==''&&loom_username_norm($v)===$target){fclose($fh);return true;}}}fclose($fh);}return false;
}
function loom_project_identity_has_explicit_legacy_evidence(string $project,array $owner,string $clientId): bool {
  $clients=array_fill_keys(loom_project_identity_client_ids_for_owner($owner,$clientId),true);if(!$clients)return false;$dir=log_dir($project);if(!is_dir($dir))return false;
  foreach(glob($dir.'/*.jsonl')?:[] as $f){$fh=@fopen($f,'rb');if(!$fh)continue;while(($line=fgets($fh))!==false){$e=json_decode($line,true);if(!is_array($e))continue;$cid=safe_token((string)($e['clientId']??''));if($cid===''||!isset($clients[$cid]))continue;$aid=(string)($e['actionId']??'');$type=(string)($e['type']??'');if($aid==='user.profile.username.save'||$type==='user.profile.username.saved'){fclose($fh);return true;}}fclose($fh);}
  return false;
}
function loom_project_identity_reconcile_legacy_row(array $row,string $project,string $clientId,array $owner): array {
  $r=loom_project_identity_row_normalize($row);
  if(($r['usernameMode']??'global')==='global'){
    if(($r['username']??null)!==null||!empty($r['usernameExplicit'])){$r['username']=null;$r['usernameExplicit']=false;$r['updatedAt']=server_timestamp();return loom_project_identity_write($r);}return $r;
  }
  if(!empty($r['usernameExplicit']))return $r;
  $name=loom_clean_username((string)($r['username']??''));$internal=$name!==''&&preg_match('/^(?:GuestHandle-|ProfileHandle-|acct_|LOOMUser-)/i',$name);
  $aliases=loom_project_identity_aliases_for_owner($clientId,$owner);$copied=$name!==''&&(isset($aliases[loom_username_norm($name)])||loom_project_identity_legacy_log_alias_match($project,$owner,$clientId,$name));
  $explicitEvidence=loom_project_identity_has_explicit_legacy_evidence($project,$owner,$clientId);
  if(($internal||$copied)&&!$explicitEvidence){$old=$name;$r['usernameMode']='global';$r['usernameExplicit']=false;$r['username']=null;$r['updatedAt']=server_timestamp();$r=loom_project_identity_write($r);if(function_exists('loom_identity_audit'))loom_identity_audit('project-identity.inherited-copy-repaired',['project'=>$project,'ownerType'=>$owner['type'],'ownerId'=>$owner['id'],'oldUsername'=>$old,'effectiveUsername'=>$r['effectiveUsername']??null]);return $r;}
  // Unknown old project values are preserved rather than guessed away. Mark them
  // explicit exactly once so future global-profile changes cannot rewrite them.
  $r['usernameExplicit']=true;$r['updatedAt']=server_timestamp();return loom_project_identity_write($r);
}
function loom_project_identity_legacy_seed(string $clientId,array $owner): ?string { return null; }
function loom_project_identity_ensure(string $project,string $clientId,bool $seedLegacy=true): array {
  $project=safe_slug($project);$clientId=safe_token($clientId);if($project===''||!project_dir($project)||$clientId==='')throw new RuntimeException('Invalid project identity request.');
  $owner=loom_project_identity_owner_for_client($clientId);loom_global_profile_ensure_for_owner($owner['type'],$owner['id']);$row=loom_project_identity_get_for_owner($project,$owner['type'],$owner['id']);
  if($row){$row=loom_project_identity_reconcile_legacy_row($row,$project,$clientId,$owner);$row['effectiveUsername']=loom_project_identity_effective_username_from_row($row);return $row;}
  $gp=loom_global_profile_ensure_for_owner($owner['type'],$owner['id']);$candidate=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($gp):(string)($gp['displayName']??'');
  if($candidate!==''&&loom_project_identity_conflict($project,$owner['type'],$owner['id'],$candidate))throw new RuntimeException('Your LOOM-wide username is already in use in this project. Change the LOOM-wide username or choose an explicit project username.');
  return loom_project_identity_write(['project'=>$project,'ownerType'=>$owner['type'],'ownerId'=>$owner['id'],'identityId'=>loom_project_identity_id($project,$owner['type'],$owner['id']),'usernameMode'=>'global','usernameExplicit'=>false,'username'=>null,'avatarMode'=>'auto','createdAt'=>server_timestamp(),'updatedAt'=>server_timestamp()]);
}
function loom_project_identity_set_username(string $project,string $clientId,string $username): array { loom_project_identity_assert_mutation_access($clientId);$username=loom_clean_username($username);if($username==='')throw new RuntimeException('Username cannot be empty.');$r=loom_project_identity_ensure($project,$clientId,true);$r['usernameMode']='project';$r['usernameExplicit']=true;$r['username']=$username;$r['updatedAt']=server_timestamp();$out=loom_project_identity_write($r);if(function_exists('loom_identity_audit'))loom_identity_audit('project-identity.username.explicitly-set',['project'=>$project,'ownerType'=>$out['ownerType'],'ownerId'=>$out['ownerId'],'username'=>$username]);return $out; }
function loom_project_identity_set_username_mode(string $project,string $clientId,string $mode): array { if(!in_array($mode,['global','project'],true))throw new RuntimeException('Invalid username mode.');loom_project_identity_assert_mutation_access($clientId);$r=loom_project_identity_ensure($project,$clientId,true);if($mode==='project'&&trim((string)($r['username']??''))===''){$gp=loom_global_profile_ensure($clientId);$r['username']=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($gp):(string)($gp['displayName']??'');}$r['usernameMode']=$mode;$r['usernameExplicit']=$mode==='project';if($mode==='global')$r['username']=null;$r['updatedAt']=server_timestamp();$out=loom_project_identity_write($r);if(function_exists('loom_identity_audit'))loom_identity_audit('project-identity.username-mode.updated',['project'=>$project,'ownerType'=>$out['ownerType'],'ownerId'=>$out['ownerId'],'mode'=>$mode]);return $out; }
function loom_project_identity_admin_set_username(string $project,string $type,string $id,string $username): array { $username=loom_clean_username($username);if($username==='')throw new RuntimeException('Username cannot be empty.');$r=loom_project_identity_get_for_owner($project,$type,$id)?:['project'=>$project,'ownerType'=>$type,'ownerId'=>$id,'identityId'=>loom_project_identity_id($project,$type,$id),'avatarMode'=>'auto','createdAt'=>server_timestamp()];$r['usernameMode']='project';$r['usernameExplicit']=true;$r['username']=$username;$r['updatedAt']=server_timestamp();return loom_project_identity_write($r); }
function loom_project_identity_admin_set_username_mode(string $project,string $type,string $id,string $mode): array { if(!in_array($mode,['global','project'],true))throw new RuntimeException('Invalid username mode.');$r=loom_project_identity_get_for_owner($project,$type,$id)?:['project'=>$project,'ownerType'=>$type,'ownerId'=>$id,'identityId'=>loom_project_identity_id($project,$type,$id),'avatarMode'=>'auto','createdAt'=>server_timestamp()];$r['usernameMode']=$mode;$r['usernameExplicit']=$mode==='project';if($mode==='global')$r['username']=null;elseif(trim((string)($r['username']??''))===''){$gp=loom_global_profile_ensure_for_owner($type,$id);$r['username']=loom_global_profile_display_name($gp);}$r['updatedAt']=server_timestamp();return loom_project_identity_write($r); }
function loom_project_identity_set_avatar_meta(string $project,string $type,string $id,array $meta): array { $r=loom_project_identity_get_for_owner($project,$type,$id)?:loom_project_identity_write(['project'=>$project,'ownerType'=>$type,'ownerId'=>$id,'identityId'=>loom_project_identity_id($project,$type,$id),'usernameMode'=>'global','usernameExplicit'=>false,'avatarMode'=>'auto','createdAt'=>server_timestamp()]);foreach(['avatarMode','customExt','mimeType','byteSize'] as $k)if(array_key_exists($k,$meta))$r[$k]=$meta[$k];$r['updatedAt']=server_timestamp();return loom_project_identity_write($r); }
function loom_project_identity_list_for_owner(string $type,string $id): array { $id=safe_token($id);if($id===''||!in_array($type,['client','user'],true))return [];$rows=[];loom_project_identity_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_project_identities WHERE owner_type=? AND owner_id=? ORDER BY updated_at DESC");$st->execute([$type,$id]);foreach($st->fetchAll() as $r){$n=loom_project_identity_row_normalize($r);$rows[$n['project']]=$n;}}catch(Throwable $e){}foreach((loom_project_identity_store()['identities']??[]) as $r){$n=loom_project_identity_row_normalize($r);if($n['ownerType']===$type&&$n['ownerId']===$id&&!isset($rows[$n['project']]))$rows[$n['project']]=$n;}foreach($rows as &$r){$pj=loom_project_effective_data($r['project']);$r['projectName']=$pj['name']??$r['project'];$r['effectiveUsername']=loom_project_identity_effective_username_from_row($r);}unset($r);return array_values($rows); }
function loom_project_identity_list_for_client(string $clientId): array { $o=loom_project_identity_owner_for_client($clientId);$rows=loom_project_identity_list_for_owner($o['type'],$o['id']);foreach($rows as &$r){$name=$r['projectName']??null;if(project_dir((string)($r['project']??'')))try{$r=loom_project_identity_ensure((string)$r['project'],$clientId,true);if($name!==null)$r['projectName']=$name;}catch(Throwable $e){}}unset($r);return $rows; }
function loom_project_identity_promote_client(string $clientId,string $userId): void { $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return;loom_project_identity_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_project_identities WHERE owner_type='client' AND owner_id=?");$st->execute([$clientId]);foreach($st->fetchAll() as $raw){$r=loom_project_identity_row_normalize($raw);if(($r['usernameMode']??'global')==='project'&&empty($r['usernameExplicit']))$r=loom_project_identity_reconcile_legacy_row($r,(string)$r['project'],$clientId,['type'=>'client','id'=>$clientId,'user'=>null]);$existing=loom_project_identity_get_for_owner($r['project'],'user',$userId);if(!$existing){$r['ownerType']='user';$r['ownerId']=$userId;$r['identityId']=loom_project_identity_id($r['project'],'user',$userId);loom_project_identity_write($r);}$pdo->prepare("DELETE FROM loom_project_identities WHERE project_slug=? AND owner_type='client' AND owner_id=?")->execute([$r['project'],$clientId]);}}catch(Throwable $e){}$s=loom_project_identity_store();$changed=false;foreach(array_keys($s['identities']??[]) as $k){$r=loom_project_identity_row_normalize($s['identities'][$k]);if($r['ownerType']!=='client'||$r['ownerId']!==$clientId)continue;if(($r['usernameMode']??'global')==='project'&&empty($r['usernameExplicit']))$r=loom_project_identity_reconcile_legacy_row($r,(string)$r['project'],$clientId,['type'=>'client','id'=>$clientId,'user'=>null]);$new=loom_project_identity_key($r['project'],'user',$userId);if(!isset($s['identities'][$new])){$r['ownerType']='user';$r['ownerId']=$userId;$r['identityId']=loom_project_identity_id($r['project'],'user',$userId);unset($r['usernameExplicitKnown']);$s['identities'][$new]=$r;}unset($s['identities'][$k]);$changed=true;}if($changed)loom_write_project_identity_store($s); }
function loom_project_identity_migrate_temp_to_db(): int { if(!loom_db_ready())return 0;$n=0;foreach((loom_project_identity_store()['identities']??[]) as $r){try{loom_project_identity_write(loom_project_identity_row_normalize($r));$n++;}catch(Throwable $e){}}return $n; }
