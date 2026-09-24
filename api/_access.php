<?php
// @loom-file release=0.15.33 revision=6 policy=package-priority
// LOOM delegated administration: immutable System Owner, delegated LOOM Admins,
// and project-scoped Admin/Manager grants for permanent accounts or guest profiles.
declare(strict_types=1);

function loom_access_file(): string { ensure_dir(loom_admin_dir()); return loom_admin_dir().'/access-control.json'; }
function loom_access_defaults(): array {
  return ['schemaVersion'=>'1.0','loomAdmins'=>[],'projectGrants'=>[],'updatedAt'=>null];
}
function loom_access_store(): array {
  $cached=$GLOBALS['loom_access_request_cache']??null;if(is_array($cached))return $cached;
  $s=read_json_file(loom_access_file()); if(!is_array($s))$s=[];
  $s=array_replace(loom_access_defaults(),$s);
  if(!is_array($s['loomAdmins']??null))$s['loomAdmins']=[];
  if(!is_array($s['projectGrants']??null))$s['projectGrants']=[];
  $GLOBALS['loom_access_request_cache']=$s;return $s;
}
function loom_access_write(array $s): void {
  $s=array_replace(loom_access_defaults(),$s);$s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();
  $json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||@file_put_contents(loom_access_file(),$json."\n",LOCK_EX)===false)throw new RuntimeException('Could not save delegated access settings.');
  @chmod(loom_access_file(),0600);$GLOBALS['loom_access_request_cache']=$s;
}
function loom_access_system_owner_state(): ?array { $s=loom_admin_identity(); return is_array($s)?$s:null; }
function loom_access_system_owner_user_id(): string { $s=loom_access_system_owner_state(); return safe_token((string)($s['userId']??'')); }
function loom_access_system_owner_client_id(): string { $s=loom_access_system_owner_state(); return safe_token((string)($s['clientId']??'')); }
function loom_access_auth_user_id(): string { $u=loom_auth_user(); return safe_token((string)($u['user_id']??$u['userId']??'')); }
function loom_access_linked_user_id(string $clientId): string { $u=loom_account_user_for_client(safe_token($clientId)); return safe_token((string)($u['user_id']??$u['userId']??'')); }
function loom_access_effective_user_id(string $clientId): string { $uid=loom_access_auth_user_id(); return $uid!==''?$uid:loom_access_linked_user_id($clientId); }
function loom_access_is_system_owner(string $clientId): bool {
  $clientId=safe_token($clientId);$state=loom_access_system_owner_state();if(!$state)return false;
  $ownerUid=loom_access_system_owner_user_id();$uid=loom_access_effective_user_id($clientId);
  if($ownerUid!==''&&$uid!==''&&hash_equals($ownerUid,$uid))return true;
  $ownerClient=loom_access_system_owner_client_id();
  if($ownerClient!==''&&$clientId!==''&&hash_equals($ownerClient,$clientId)&&loom_admin_cookie_valid()){
    if($ownerUid===''||$uid===''||hash_equals($ownerUid,$uid))return true;
  }
  return false;
}
function loom_access_loom_admin_row(string $userId): ?array {
  $uid=safe_token($userId);if($uid==='')return null;$row=loom_access_store()['loomAdmins'][$uid]??null;return is_array($row)&&($row['enabled']??true)?$row:null;
}
function loom_access_user_is_loom_admin(string $userId): bool {
  $uid=safe_token($userId);if($uid==='')return false;
  if($uid===loom_access_system_owner_user_id())return true;
  if(loom_access_loom_admin_row($uid))return true;
  // Safe upgrade path: pre-delegation permanent Admin accounts remain LOOM Admins.
  $u=loom_account_user_by_id($uid);return is_array($u)&&strcasecmp((string)($u['privilege']??'User'),'Admin')===0;
}
function loom_access_client_is_loom_admin(string $clientId): bool {
  if(loom_access_is_system_owner($clientId))return true;
  $uid=loom_access_effective_user_id($clientId);return $uid!==''&&loom_access_user_is_loom_admin($uid);
}
function loom_access_guest_profile_id_for_client(string $clientId): string {
  if(!function_exists('loom_guest_profile_for_client'))return '';$p=loom_guest_profile_for_client(safe_token($clientId));return is_array($p)?safe_token((string)($p['guestProfileId']??'')):'';
}
function loom_access_subject_keys_for_client(string $clientId): array {
  $clientId=safe_token($clientId);$keys=[];$uid=loom_access_effective_user_id($clientId);if($uid!=='')$keys[]='user:'.$uid;
  $gpid=loom_access_guest_profile_id_for_client($clientId);if($gpid!=='')$keys[]='guest:'.$gpid;
  if($clientId!=='')$keys[]='client:'.$clientId;
  return array_values(array_unique($keys));
}
function loom_access_role_capabilities(string $role): array {
  return match($role){
    'project-admin'=>['project.view','project.settings','project.modules','project.content','project.users','html-framer.manage'],
    'project-manager'=>['project.view','project.settings','project.modules','project.content','html-framer.manage'],
    default=>[]
  };
}
function loom_access_project_grant_for_client(string $clientId,string $project): ?array {
  $project=safe_slug($project);if($project==='')return null;$grants=loom_access_store()['projectGrants'][$project]??[];if(!is_array($grants))return null;
  foreach(loom_access_subject_keys_for_client($clientId) as $key){$row=$grants[$key]??null;if(is_array($row)&&($row['enabled']??true)){return ['subjectKey'=>$key]+$row;}}
  return null;
}
function loom_access_project_role(string $clientId,string $project): string {
  if(loom_access_is_system_owner($clientId))return 'system-owner';
  if(loom_access_client_is_loom_admin($clientId))return 'loom-admin';
  $g=loom_access_project_grant_for_client($clientId,$project);$role=(string)($g['role']??'');return in_array($role,['project-admin','project-manager'],true)?$role:'member';
}
function loom_access_effective_capabilities(string $clientId,string $project=''): array {
  if(loom_access_is_system_owner($clientId))return ['system.owner','loom.admin','loom.admins.manage','loom.settings','loom.database','loom.identities','loom.activity','loom.backup','loom.backup.full','project.view','project.settings','project.modules','project.content','project.users','project.access','html-framer.manage'];
  if(loom_access_client_is_loom_admin($clientId))return ['loom.admin','loom.settings','loom.database','loom.identities','loom.activity','loom.backup','project.view','project.settings','project.modules','project.content','project.users','project.access','html-framer.manage'];
  if($project!==''){$g=loom_access_project_grant_for_client($clientId,$project);if($g){$caps=loom_access_role_capabilities((string)($g['role']??''));foreach(($g['capabilities']??[]) as $cap)if(is_string($cap)&&$cap!=='')$caps[]=$cap;return array_values(array_unique($caps));}}
  return [];
}
function loom_access_has_capability(string $clientId,string $capability,string $project=''): bool { return in_array($capability,loom_access_effective_capabilities($clientId,$project),true); }
function loom_access_can_manage_project(string $clientId,string $project): bool { return loom_access_has_capability($clientId,'project.settings',$project); }
function loom_require_project_capability(string $clientId,string $project,string $capability='project.settings'): void {
  if(!loom_access_has_capability(safe_token($clientId),$capability,safe_slug($project)))json_out(['ok'=>false,'error'=>'project-admin-access-required','requiredCapability'=>$capability,'project'=>safe_slug($project)],403);
}
function loom_access_subject_key(string $type,string $id): string {
  $type=in_array($type,['user','guest','client'],true)?$type:'';$id=safe_token($id);if($type===''||$id==='')throw new RuntimeException('Invalid delegated-access subject.');return $type.':'.$id;
}
function loom_access_grant_project(string $project,string $type,string $id,string $role,string $actorClientId,array $capabilities=[]): array {
  $project=safe_slug($project);if($project===''||!project_dir($project))throw new RuntimeException('Project not found.');
  if($role!=='project-admin')throw new RuntimeException('Project Manager grants are retired. Grant Project Admin access instead. Existing legacy Manager grants remain unchanged until revoked.');
  $actorRole=loom_access_project_role($actorClientId,$project);
  if(!in_array($actorRole,['system-owner','loom-admin'],true))throw new RuntimeException('Only the System Owner or a LOOM Admin can grant Project Admin access.');
  $key=loom_access_subject_key($type,$id);$s=loom_access_store();if(!isset($s['projectGrants'][$project])||!is_array($s['projectGrants'][$project]))$s['projectGrants'][$project]=[];
  $caps=[];foreach($capabilities as $cap)if(is_string($cap)&&in_array($cap,loom_access_role_capabilities($role),true))$caps[]=$cap;
  $s['projectGrants'][$project][$key]=['role'=>$role,'capabilities'=>array_values(array_unique($caps)),'enabled'=>true,'grantedAt'=>server_timestamp(),'grantedBy'=>loom_access_effective_user_id($actorClientId)?:safe_token($actorClientId)];loom_access_write($s);
  loom_audit_record('access.project.granted',['clientId'=>$actorClientId,'project'=>$project,'details'=>['subjectKey'=>$key,'role'=>$role]],'Project access delegated.');
  return $s['projectGrants'][$project][$key];
}
function loom_access_revoke_project(string $project,string $type,string $id,string $actorClientId): void {
  $project=safe_slug($project);$role=loom_access_project_role($actorClientId,$project);if(!in_array($role,['system-owner','loom-admin'],true))throw new RuntimeException('Only the System Owner or a LOOM Admin can revoke Project Admin access.');
  $key=loom_access_subject_key($type,$id);$s=loom_access_store();$existing=$s['projectGrants'][$project][$key]??null;
  unset($s['projectGrants'][$project][$key]);if(empty($s['projectGrants'][$project]))unset($s['projectGrants'][$project]);loom_access_write($s);
  loom_audit_record('access.project.revoked',['clientId'=>$actorClientId,'project'=>$project,'details'=>['subjectKey'=>$key]],'Project access revoked.');
}
function loom_access_grant_loom_admin(string $userId,string $actorClientId): array {
  if(!loom_access_is_system_owner($actorClientId))throw new RuntimeException('Only the System Owner can create LOOM Admins.');
  $uid=safe_token($userId);if($uid===''||!loom_account_user_by_id($uid))throw new RuntimeException('Choose a permanent LOOM account.');
  if($uid===loom_access_system_owner_user_id())throw new RuntimeException('The System Owner already has the highest authority.');
  $s=loom_access_store();$s['loomAdmins'][$uid]=['enabled'=>true,'grantedAt'=>server_timestamp(),'grantedBy'=>loom_access_effective_user_id($actorClientId)?:safe_token($actorClientId)];loom_access_write($s);
  // Keep legacy account privilege coherent for existing authorization surfaces.
  if(loom_db_ready()){try{loom_db_pdo(true)->prepare("UPDATE loom_users SET privilege='Admin',updated_at=UTC_TIMESTAMP(3) WHERE user_id=?")->execute([$uid]);}catch(Throwable $e){}}
  else{$t=loom_temp_account_store();if(isset($t['users'][$uid])){$t['users'][$uid]['privilege']='Admin';$t['users'][$uid]['updatedAt']=server_timestamp();loom_write_temp_account_store($t);}}
  loom_audit_record('access.loom-admin.granted',['clientId'=>$actorClientId,'userId'=>$uid],'LOOM Admin access granted by System Owner.');return $s['loomAdmins'][$uid];
}
function loom_access_revoke_loom_admin(string $userId,string $actorClientId): void {
  if(!loom_access_is_system_owner($actorClientId))throw new RuntimeException('Only the System Owner can revoke LOOM Admins.');
  $uid=safe_token($userId);if($uid===''||$uid===loom_access_system_owner_user_id())throw new RuntimeException('System Owner authority cannot be revoked.');
  $s=loom_access_store();unset($s['loomAdmins'][$uid]);loom_access_write($s);
  if(loom_db_ready()){try{loom_db_pdo(true)->prepare("UPDATE loom_users SET privilege='User',updated_at=UTC_TIMESTAMP(3) WHERE user_id=?")->execute([$uid]);}catch(Throwable $e){}}
  else{$t=loom_temp_account_store();if(isset($t['users'][$uid])){$t['users'][$uid]['privilege']='User';$t['users'][$uid]['updatedAt']=server_timestamp();loom_write_temp_account_store($t);}}
  loom_audit_record('access.loom-admin.revoked',['clientId'=>$actorClientId,'userId'=>$uid],'LOOM Admin access revoked by System Owner.');
}
function loom_access_global_profile_map(): array {
  static $map=null;if(is_array($map))return $map;$map=[];
  if(function_exists('loom_global_profile_list_all'))foreach(loom_global_profile_list_all() as $profile){
    if(!is_array($profile))continue;$type=(string)($profile['ownerType']??'');$id=safe_token((string)($profile['ownerId']??''));
    if($id!==''&&in_array($type,['user','client'],true))$map[$type.'|'.$id]=$profile;
  }
  return $map;
}
function loom_access_profile_from_map(string $type,string $id,array $map): ?array {
  $id=safe_token($id);$row=$map[$type.'|'.$id]??null;return is_array($row)?$row:null;
}
function loom_access_user_catalog(): array {
  $rows=[];$raw=[];
  if(loom_db_ready())try{$q=loom_db_pdo(true)->query("SELECT user_id,username,email,privilege,created_at,updated_at FROM loom_users ORDER BY COALESCE(username,email,user_id)");foreach($q->fetchAll() as $u)$raw[(string)$u['user_id']]=$u;}catch(Throwable $e){}
  if(!$raw)foreach((loom_temp_account_store()['users']??[]) as $uid=>$u){$u['user_id']=$u['user_id']??$u['userId']??$uid;$raw[(string)$uid]=$u;}
  $profiles=loom_access_global_profile_map();
  foreach($raw as $uid=>$u){$gp=loom_access_profile_from_map('user',(string)$uid,$profiles);$name=loom_clean_username((string)($gp['username']??$u['username']??''));$email=(string)($u['email']??'');$rows[]=['type'=>'user','id'=>$uid,'label'=>$name!==''?$name:($email!==''?$email:$uid),'secondary'=>$email,'privilege'=>(string)($u['privilege']??'User')];}
  usort($rows,fn($a,$b)=>strcasecmp((string)$a['label'],(string)$b['label']));return $rows;
}
function loom_access_guest_catalog(): array {
  if(!function_exists('loom_guest_profiles_store'))return [];$s=loom_guest_profiles_store();$out=[];$profiles=loom_access_global_profile_map();
  foreach(($s['profiles']??[]) as $pid=>$p){if(!is_array($p))continue;$pub=loom_guest_profile_public($p);$cid=safe_token((string)($pub['currentClientId']??''));$gp=$cid!==''?loom_access_profile_from_map('client',$cid,$profiles):null;$username=loom_clean_username((string)($gp['username']??''));$fallback=(string)($pub['displayName']??'Guest');$out[]=['type'=>'guest','id'=>(string)$pid,'label'=>$username!==''?$username:$fallback,'secondary'=>$cid,'currentClientId'=>$cid?:null,'generationStatus'=>$pub['generationStatus']??'active'];}
  usort($out,fn($a,$b)=>strcasecmp((string)$a['label'],(string)$b['label']));return $out;
}
function loom_access_public_state(string $clientId,string $project='',bool $includeSubjects=false): array {
  $project=safe_slug($project);$s=loom_access_store();$uid=loom_access_effective_user_id($clientId);$ownerUid=loom_access_system_owner_user_id();$profiles=loom_access_global_profile_map();
  $loomAdmins=[];foreach(($s['loomAdmins']??[]) as $id=>$row)if(is_array($row)&&($row['enabled']??true)){$u=loom_account_user_by_id((string)$id);$loomAdmins[]=['userId'=>$id,'label'=>(string)($u['username']??$u['email']??$id),'email'=>$u['email']??null,'grantedAt'=>$row['grantedAt']??null];}
  $grants=$project!==''&&is_array($s['projectGrants'][$project]??null)?$s['projectGrants'][$project]:[];$projectRows=[];foreach($grants as $key=>$row){if(!is_array($row))continue;[$type,$id]=array_pad(explode(':',(string)$key,2),2,'');$label=$id;if($type==='user'){$u=loom_account_user_by_id($id);$gp=loom_access_profile_from_map('user',$id,$profiles);$label=(string)($gp['username']??$u['username']??$u['email']??$id);}elseif($type==='guest'){$g=loom_guest_profile_get($id);$pub=is_array($g)?loom_guest_profile_public($g):[];$cid=safe_token((string)($pub['currentClientId']??''));$gp=$cid!==''?loom_access_profile_from_map('client',$cid,$profiles):null;$label=(string)($gp['username']??$pub['displayName']??$id);} $projectRows[]=['subjectKey'=>$key,'type'=>$type,'id'=>$id,'label'=>$label,'role'=>$row['role']??'project-manager','capabilities'=>$row['capabilities']??[],'grantedAt'=>$row['grantedAt']??null];}
  $isOwner=loom_access_is_system_owner($clientId);$isLoomAdmin=loom_access_client_is_loom_admin($clientId);$globalView=$isOwner||$isLoomAdmin;
  $out=['systemOwner'=>$globalView?['userId'=>$ownerUid?:null,'clientId'=>loom_access_system_owner_client_id()?:null,'isCurrent'=>$isOwner]:['isCurrent'=>false],'current'=>['userId'=>$uid?:null,'role'=>$project!==''?loom_access_project_role($clientId,$project):($isOwner?'system-owner':($isLoomAdmin?'loom-admin':'member')),'capabilities'=>loom_access_effective_capabilities($clientId,$project)],'loomAdmins'=>$globalView?$loomAdmins:[],'project'=>$project?:null,'projectGrants'=>$projectRows,'canManageLoomAdmins'=>$isOwner,'canViewLoomAdmins'=>$globalView,'canManageProject'=>$project!==''&&($isOwner||$isLoomAdmin)];if($includeSubjects)$out['subjects']=array_merge(loom_access_user_catalog(),loom_access_guest_catalog());return $out;
}
