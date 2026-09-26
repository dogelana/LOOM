<?php
// @loom-file release=0.15.58 revision=8 policy=package-priority
// LOOM delegated administration: immutable System Owner, delegated LOOM Admins,
// and project-scoped Admin/Manager grants for permanent accounts or guest profiles.
declare(strict_types=1);

function loom_access_file(): string { ensure_dir(loom_admin_dir()); return loom_admin_dir().'/access-control.json'; }
function loom_access_defaults(): array {
  return ['schemaVersion'=>'1.1','loomAdmins'=>[],'projectGrants'=>[],'projectVisibility'=>[],'projectViewGrants'=>[],'updatedAt'=>null];
}
function loom_access_store(): array {
  $cached=$GLOBALS['loom_access_request_cache']??null;if(is_array($cached))return $cached;
  $s=read_json_file(loom_access_file()); if(!is_array($s))$s=[];
  $s=array_replace(loom_access_defaults(),$s);
  if(!is_array($s['loomAdmins']??null))$s['loomAdmins']=[];
  if(!is_array($s['projectGrants']??null))$s['projectGrants']=[];
  if(!is_array($s['projectVisibility']??null))$s['projectVisibility']=[];
  if(!is_array($s['projectViewGrants']??null))$s['projectViewGrants']=[];
  $GLOBALS['loom_access_request_cache']=$s;return $s;
}
function loom_access_write(array $s): void {
  $s=array_replace(loom_access_defaults(),$s);$s['schemaVersion']='1.1';$s['updatedAt']=server_timestamp();
  $json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||@file_put_contents(loom_access_file(),$json."\n",LOCK_EX)===false)throw new RuntimeException('Could not save delegated access settings.');
  @chmod(loom_access_file(),0600);$GLOBALS['loom_access_request_cache']=$s;
}
function loom_access_system_owner_state(): ?array { $s=loom_admin_identity(); return is_array($s)?$s:null; }
function loom_access_system_owner_user_id(): string { $s=loom_access_system_owner_state(); return safe_token((string)($s['userId']??'')); }
function loom_access_system_owner_client_id(): string { $s=loom_access_system_owner_state(); return safe_token((string)($s['clientId']??'')); }
function loom_access_reconcile_system_owner_identity(string $requestClientId=''): array {
  $requestClientId=safe_token($requestClientId);$state=loom_access_system_owner_state();if(!$state)return ['bound'=>false,'recoveredGuestId'=>null,'state'=>null];
  // First use the long-standing authenticated bootstrap reconciliation path.
  if($requestClientId!=='')try{loom_reconcile_admin_identity($requestClientId);}catch(Throwable $e){}
  $state=loom_access_system_owner_state()?:$state;$ownerClient=safe_token((string)($state['clientId']??''));$ownerUser=safe_token((string)($state['userId']??''));$bound=false;
  // If the owner is still browser-backed, accept only an account relation that
  // remains resolvable server-side. Historical payloads can suggest a candidate,
  // but can never resurrect a deleted/nonexistent account.
  if($ownerUser===''&&$ownerClient!==''){
    $candidates=[];$linked=loom_account_user_for_client($ownerClient);$linkedId=safe_token((string)($linked['user_id']??$linked['userId']??''));if($linkedId!==''&&loom_account_user_by_id($linkedId))$candidates[$linkedId]=true;
    if($requestClientId!==''&&hash_equals($ownerClient,$requestClientId)){$auth=loom_auth_user();$authId=safe_token((string)($auth['user_id']??$auth['userId']??''));if($authId!==''&&loom_account_user_by_id($authId)&&loom_admin_cookie_valid())$candidates[$authId]=true;}
    $profileFile=loom_data_dir().'/users/'.hash('sha256',$ownerClient).'.json';$legacy=read_json_file($profileFile);$legacyId=safe_token((string)($legacy['userId']??$legacy['user_id']??''));if($legacyId!==''&&loom_account_user_by_id($legacyId))$candidates[$legacyId]=true;
    if(count($candidates)===1){$uid=(string)array_key_first($candidates);$state['userId']=$uid;$state['ownerBindingUpdatedAt']=server_timestamp();$state['ownerBindingMethod']='verified-client-account-reconciliation';loom_write_admin_identity($state);try{loom_bind_client_to_user($ownerClient,$uid);}catch(Throwable $e){}if(loom_db_ready()){try{loom_db_pdo(true)->prepare("UPDATE loom_users SET privilege='Admin',updated_at=UTC_TIMESTAMP(3) WHERE user_id=?")->execute([$uid]);}catch(Throwable $e){}}else{$t=loom_temp_account_store();if(isset($t['users'][$uid])){$t['users'][$uid]['privilege']='Admin';$t['users'][$uid]['updatedAt']=server_timestamp();loom_write_temp_account_store($t);}}$ownerUser=$uid;$bound=true;}
  }
  $recoveredGuestId=null;
  // A browser-backed owner must always have a canonical person wrapper in the
  // unified directory. Cleanup in older releases could delete that Guest while
  // leaving the owner pointer alive. Recreate only around the authoritative
  // owner client; do not infer or merge any other person.
  if($ownerUser===''&&$ownerClient!==''&&function_exists('loom_guest_ensure_for_client')){try{$g=loom_guest_for_client($ownerClient);if(!$g)$g=loom_guest_ensure_for_client($ownerClient);$root=is_array($g)?loom_guest_root($g):null;$recoveredGuestId=safe_token((string)($root['guestId']??''));}catch(Throwable $e){}}
  return ['bound'=>$bound,'recoveredGuestId'=>$recoveredGuestId?:null,'state'=>loom_access_system_owner_state()];
}
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
function loom_access_visible_profile_name(?array $profile,string $fallback=''): string {
  if(is_array($profile)){
    if(function_exists('loom_global_profile_display_name')){$n=trim((string)loom_global_profile_display_name($profile));if($n!=='')return $n;}
    foreach(['displayName','display_name','username'] as $k){$n=trim((string)($profile[$k]??''));if($n!=='')return $n;}
  }
  return trim($fallback);
}
function loom_access_project_visibility(string $project): string {
  $project=safe_slug($project);if($project==='')return 'public';$row=loom_access_store()['projectVisibility'][$project]??null;
  $v=is_array($row)?strtolower((string)($row['visibility']??'public')):strtolower((string)$row);return $v==='private'?'private':'public';
}
function loom_access_project_is_private(string $project): bool { return loom_access_project_visibility($project)==='private'; }
function loom_access_project_view_grant_for_key(string $project,string $key): ?array {
  $project=safe_slug($project);$key=trim($key);if($project===''||$key==='')return null;$rows=loom_access_store()['projectViewGrants'][$project]??[];$r=is_array($rows)?($rows[$key]??null):null;return is_array($r)&&($r['enabled']??true)?$r:null;
}
function loom_access_project_view_grant_for_client(string $clientId,string $project): ?array {
  foreach(loom_access_subject_keys_for_client($clientId) as $key){$r=loom_access_project_view_grant_for_key($project,$key);if($r)return ['subjectKey'=>$key]+$r;}return null;
}
function loom_access_subject_can_view_private_project(string $project,string $type,string $id): bool {
  $project=safe_slug($project);$type=strtolower(trim($type));$id=safe_token($id);if($project===''||$id==='')return false;
  if($type==='user'){
    if($id===loom_access_system_owner_user_id()||loom_access_user_is_loom_admin($id))return true;
    $rows=loom_access_store()['projectGrants'][$project]??[];if(is_array($rows)){ $r=$rows['user:'.$id]??null;if(is_array($r)&&($r['enabled']??true))return true; }
    return loom_access_project_view_grant_for_key($project,'user:'.$id)!==null;
  }
  if($type==='guest'){
    $rows=loom_access_store()['projectGrants'][$project]??[];if(is_array($rows)){ $r=$rows['guest:'.$id]??null;if(is_array($r)&&($r['enabled']??true))return true; }
    return loom_access_project_view_grant_for_key($project,'guest:'.$id)!==null;
  }
  if($type==='client'){
    $rows=loom_access_store()['projectGrants'][$project]??[];if(is_array($rows)){ $r=$rows['client:'.$id]??null;if(is_array($r)&&($r['enabled']??true))return true; }
    return loom_access_project_view_grant_for_key($project,'client:'.$id)!==null;
  }
  return false;
}
function loom_access_can_view_project(string $clientId,string $project): bool {
  $project=safe_slug($project);if($project===''||!project_dir($project))return false;if(!loom_access_project_is_private($project))return true;
  $clientId=safe_token($clientId);if($clientId!==''&&(loom_access_is_system_owner($clientId)||loom_access_client_is_loom_admin($clientId)))return true;
  $uid=loom_access_auth_user_id();if($uid!==''&&loom_access_subject_can_view_private_project($project,'user',$uid))return true;
  if($clientId!==''&&loom_access_project_grant_for_client($clientId,$project))return true;
  if($clientId!==''&&loom_access_project_view_grant_for_client($clientId,$project))return true;
  return false;
}
function loom_access_set_project_visibility(string $project,string $visibility,string $actorClientId): array {
  $project=safe_slug($project);$visibility=strtolower(trim($visibility));if($project===''||!project_dir($project))throw new RuntimeException('Project not found.');if(!loom_access_is_system_owner($actorClientId))throw new RuntimeException('Only the System Owner can change project visibility.');if(!in_array($visibility,['public','private'],true))throw new RuntimeException('Visibility must be public or private.');
  $s=loom_access_store();$s['projectVisibility'][$project]=['visibility'=>$visibility,'updatedAt'=>server_timestamp(),'updatedBy'=>loom_access_effective_user_id($actorClientId)?:safe_token($actorClientId)];loom_access_write($s);loom_audit_record('access.project.visibility',['clientId'=>$actorClientId,'project'=>$project,'details'=>['visibility'=>$visibility]],'Project visibility changed.');return $s['projectVisibility'][$project];
}
function loom_access_grant_project_view(string $project,string $type,string $id,string $actorClientId): array {
  $project=safe_slug($project);if($project===''||!project_dir($project))throw new RuntimeException('Project not found.');if(!loom_access_is_system_owner($actorClientId))throw new RuntimeException('Only the System Owner can grant private project access.');if(!in_array($type,['user','guest'],true))throw new RuntimeException('Choose a permanent account or active Guest Identity.');
  $key=loom_access_subject_key($type,$id);$s=loom_access_store();if(!isset($s['projectViewGrants'][$project])||!is_array($s['projectViewGrants'][$project]))$s['projectViewGrants'][$project]=[];$s['projectViewGrants'][$project][$key]=['enabled'=>true,'grantedAt'=>server_timestamp(),'grantedBy'=>loom_access_effective_user_id($actorClientId)?:safe_token($actorClientId)];loom_access_write($s);loom_audit_record('access.project.view.granted',['clientId'=>$actorClientId,'project'=>$project,'details'=>['subjectKey'=>$key]],'Private project access granted.');return $s['projectViewGrants'][$project][$key];
}
function loom_access_revoke_project_view(string $project,string $type,string $id,string $actorClientId): void {
  $project=safe_slug($project);if(!loom_access_is_system_owner($actorClientId))throw new RuntimeException('Only the System Owner can revoke private project access.');$key=loom_access_subject_key($type,$id);$s=loom_access_store();unset($s['projectViewGrants'][$project][$key]);if(empty($s['projectViewGrants'][$project]))unset($s['projectViewGrants'][$project]);loom_access_write($s);loom_audit_record('access.project.view.revoked',['clientId'=>$actorClientId,'project'=>$project,'details'=>['subjectKey'=>$key]],'Private project access revoked.');
}
function loom_access_project_asset_signature(string $project,string $path): string {
  $project=safe_slug($project);$path=ltrim(str_replace('\\','/',$path),'/');if($project===''||$path===''||str_contains($path,'..'))return '';return substr(hash_hmac('sha256','loom-private-project-asset|'.$project.'|'.$path,loom_admin_nav_secret()),0,48);
}
function loom_access_verify_project_asset_signature(string $project,string $path,string $signature): bool {
  $expected=loom_access_project_asset_signature($project,$path);$signature=strtolower(preg_replace('/[^a-f0-9]/i','',$signature)??'');return $expected!==''&&strlen($signature)===strlen($expected)&&hash_equals($expected,$signature);
}
function loom_access_project_entry_signature(string $project,string $clientId): string {
  $project=safe_slug($project);$clientId=safe_token($clientId);if($project===''||$clientId==='')return '';return substr(hash_hmac('sha256','loom-project-entry|'.$project.'|'.$clientId,loom_admin_nav_secret()),0,48);
}
function loom_access_verify_project_entry_signature(string $project,string $clientId,string $signature): bool {
  $expected=loom_access_project_entry_signature($project,$clientId);$signature=strtolower(preg_replace('/[^a-f0-9]/i','',$signature)??'');return $expected!==''&&strlen($signature)===strlen($expected)&&hash_equals($expected,$signature);
}
function loom_access_project_entry_url(string $project,string $clientId=''): string {
  $url=loom_project_public_url($project);$project=safe_slug($project);$clientId=safe_token($clientId);if(!loom_access_project_is_private($project)||$clientId==='')return $url;$sep=str_contains($url,'?')?'&':'?';return $url.$sep.'clientId='.rawurlencode($clientId).'&pa='.rawurlencode(loom_access_project_entry_signature($project,$clientId));
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
function loom_access_promote_client_to_user(string $clientId,string $userId): array {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return ['moved'=>0,'subjects'=>[]];
  $subjectKeys=['client:'.$clientId];$guestProfileId='';
  if(function_exists('loom_guest_profile_for_client')){$gp=loom_guest_profile_for_client($clientId);$guestProfileId=safe_token((string)($gp['guestProfileId']??''));if($guestProfileId!=='')$subjectKeys[]='guest:'.$guestProfileId;}
  $dest='user:'.$userId;$s=loom_access_store();$moved=0;
  foreach(['projectGrants','projectViewGrants'] as $bucket){
    if(!isset($s[$bucket])||!is_array($s[$bucket]))continue;
    foreach($s[$bucket] as $project=>$rows){if(!is_array($rows))continue;
      $candidate=null;
      foreach($subjectKeys as $key)if(isset($rows[$key])&&is_array($rows[$key])){$candidate=$rows[$key];break;}
      if(!$candidate)continue;
      if(!isset($s[$bucket][$project][$dest])||!is_array($s[$bucket][$project][$dest]))$s[$bucket][$project][$dest]=$candidate;
      foreach($subjectKeys as $key)if(isset($s[$bucket][$project][$key])){unset($s[$bucket][$project][$key]);$moved++;}
      if(isset($s[$bucket][$project][$dest])&&is_array($s[$bucket][$project][$dest])){
        $s[$bucket][$project][$dest]['promotedFromClientId']=$clientId;
        if($guestProfileId!=='')$s[$bucket][$project][$dest]['promotedFromGuestProfileId']=$guestProfileId;
        $s[$bucket][$project][$dest]['updatedAt']=server_timestamp();
      }
      if(empty($s[$bucket][$project]))unset($s[$bucket][$project]);
    }
  }
  if($moved>0){loom_access_write($s);if(function_exists('loom_audit_record'))loom_audit_record('access.subject.promoted',['clientId'=>$clientId,'userId'=>$userId,'details'=>['sourceSubjects'=>$subjectKeys,'moved'=>$moved]],'Delegated access followed guest-to-account promotion.');}
  return ['moved'=>$moved,'subjects'=>$subjectKeys];
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
  foreach($raw as $uid=>$u){$gp=loom_access_profile_from_map('user',(string)$uid,$profiles);$email=(string)($u['email']??'');$fallback=loom_clean_username((string)($u['username']??''));$name=loom_access_visible_profile_name($gp,$fallback!==''?$fallback:($email!==''?$email:$uid));$rows[]=['type'=>'user','id'=>$uid,'label'=>$name!==''?$name:($email!==''?$email:$uid),'secondary'=>$email,'privilege'=>(string)($u['privilege']??'User')];}
  usort($rows,fn($a,$b)=>strcasecmp((string)$a['label'],(string)$b['label']));return $rows;
}
function loom_access_guest_catalog(): array {
  if(!function_exists('loom_guest_profiles_store'))return [];$s=loom_guest_profiles_store();$out=[];$profiles=loom_access_global_profile_map();
  foreach(($s['profiles']??[]) as $pid=>$p){if(!is_array($p))continue;$pub=loom_guest_profile_public($p);$status=strtolower((string)($pub['generationStatus']??'active'));if($status!=='active')continue;$cid=safe_token((string)($pub['currentClientId']??''));if($cid==='')continue;
    // A browser already attached to a permanent account is lineage, not a second selectable person.
    $linked=loom_account_user_for_client($cid);$linkedId=safe_token((string)($linked['user_id']??$linked['userId']??''));if($linkedId!=='')continue;
    $guest=loom_guest_for_client($cid);$root=is_array($guest)?loom_guest_root($guest):null;if(is_array($root)&&in_array((string)($root['status']??''),['attached','standby','merged'],true))continue;
    $gp=loom_access_profile_from_map('client',$cid,$profiles);$fallback=loom_clean_username((string)($pub['displayName']??''));$name=$fallback!==''?$fallback:loom_access_visible_profile_name($gp,'Guest');$out[]=['type'=>'guest','id'=>(string)$pid,'label'=>$name!==''?$name:'Guest','secondary'=>$cid,'currentClientId'=>$cid,'generationStatus'=>$status];}
  usort($out,fn($a,$b)=>strcasecmp((string)$a['label'],(string)$b['label']));return $out;
}

function loom_access_public_state(string $clientId,string $project='',bool $includeSubjects=false): array {
  $project=safe_slug($project);$s=loom_access_store();$uid=loom_access_effective_user_id($clientId);$ownerUid=loom_access_system_owner_user_id();$profiles=loom_access_global_profile_map();
  $loomAdmins=[];foreach(($s['loomAdmins']??[]) as $id=>$row)if(is_array($row)&&($row['enabled']??true)){$u=loom_account_user_by_id((string)$id);$gp=loom_access_profile_from_map('user',(string)$id,$profiles);$fallback=(string)($u['email']??$u['username']??$id);$loomAdmins[]=['userId'=>$id,'label'=>loom_access_visible_profile_name($gp,$fallback),'email'=>$u['email']??null,'grantedAt'=>$row['grantedAt']??null];}
  $grants=$project!==''&&is_array($s['projectGrants'][$project]??null)?$s['projectGrants'][$project]:[];$projectRows=[];foreach($grants as $key=>$row){if(!is_array($row))continue;[$type,$id]=array_pad(explode(':',(string)$key,2),2,'');$label=$id;if($type==='user'){$u=loom_account_user_by_id($id);$gp=loom_access_profile_from_map('user',$id,$profiles);$label=loom_access_visible_profile_name($gp,(string)($u['email']??$u['username']??$id));}elseif($type==='guest'){$g=loom_guest_profile_get($id);$pub=is_array($g)?loom_guest_profile_public($g):[];$cid=safe_token((string)($pub['currentClientId']??''));$gp=$cid!==''?loom_access_profile_from_map('client',$cid,$profiles):null;$label=trim((string)($pub['displayName']??''))?:loom_access_visible_profile_name($gp,$id);} $projectRows[]=['subjectKey'=>$key,'type'=>$type,'id'=>$id,'label'=>$label,'role'=>$row['role']??'project-manager','capabilities'=>$row['capabilities']??[],'grantedAt'=>$row['grantedAt']??null];}
  $viewRows=[];$viewGrants=$project!==''&&is_array($s['projectViewGrants'][$project]??null)?$s['projectViewGrants'][$project]:[];foreach($viewGrants as $key=>$row){if(!is_array($row)||!($row['enabled']??true))continue;[$type,$id]=array_pad(explode(':',(string)$key,2),2,'');$label=$id;if($type==='user'){$u=loom_account_user_by_id($id);$gp=loom_access_profile_from_map('user',$id,$profiles);$label=loom_access_visible_profile_name($gp,(string)($u['email']??$u['username']??$id));}elseif($type==='guest'){$g=loom_guest_profile_get($id);$pub=is_array($g)?loom_guest_profile_public($g):[];$cid=safe_token((string)($pub['currentClientId']??''));$gp=$cid!==''?loom_access_profile_from_map('client',$cid,$profiles):null;$label=trim((string)($pub['displayName']??''))?:loom_access_visible_profile_name($gp,$id);} $viewRows[]=['subjectKey'=>$key,'type'=>$type,'id'=>$id,'label'=>$label,'grantedAt'=>$row['grantedAt']??null];}
  $isOwner=loom_access_is_system_owner($clientId);$isLoomAdmin=loom_access_client_is_loom_admin($clientId);$globalView=$isOwner||$isLoomAdmin;$visibility=$project!==''?loom_access_project_visibility($project):null;
  $out=['systemOwner'=>$globalView?['userId'=>$ownerUid?:null,'clientId'=>loom_access_system_owner_client_id()?:null,'isCurrent'=>$isOwner]:['isCurrent'=>false],'current'=>['userId'=>$uid?:null,'role'=>$project!==''?loom_access_project_role($clientId,$project):($isOwner?'system-owner':($isLoomAdmin?'loom-admin':'member')),'capabilities'=>loom_access_effective_capabilities($clientId,$project)],'loomAdmins'=>$globalView?$loomAdmins:[],'project'=>$project?:null,'projectGrants'=>$projectRows,'projectVisibility'=>$visibility,'projectPrivate'=>$visibility==='private','projectViewGrants'=>$viewRows,'canManageLoomAdmins'=>$isOwner,'canViewLoomAdmins'=>$globalView,'canManageProject'=>$project!==''&&($isOwner||$isLoomAdmin),'canManageProjectVisibility'=>$project!==''&&$isOwner,'canManagePrivateAccess'=>$project!==''&&$isOwner];if($includeSubjects)$out['subjects']=array_merge(loom_access_user_catalog(),loom_access_guest_catalog());return $out;
}

