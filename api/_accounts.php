<?php
// @loom-file release=0.15.49 revision=14 policy=package-priority
declare(strict_types=1);

function loom_accounts_dir(): string { return loom_data_dir().'/accounts'; }
function loom_accounts_file(): string { return loom_accounts_dir().'/store.json'; }
function loom_auth_cookie_name(): string { return 'loom_auth_token'; }

function loom_clean_username(string $value): string {
  $value=trim(preg_replace('/[\x00-\x1F\x7F]/u','',$value) ?? '');
  $value=preg_replace('/\s+/u',' ',$value) ?? $value;
  if(function_exists('mb_substr'))$value=mb_substr($value,0,40,'UTF-8'); else $value=substr($value,0,40);
  return trim($value);
}
function loom_username_norm(string $value): string {
  $value=loom_clean_username($value);
  return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);
}
function loom_email_norm(string $value): string { return strtolower(trim($value)); }

function loom_temp_account_store(): array {
  $d=read_json_file(loom_accounts_file())?:[];
  return array_replace(['schemaVersion'=>'1.0','users'=>[],'clients'=>[],'sessions'=>[],'usernames'=>[]],$d);
}
function loom_write_temp_account_store(array $store): void {
  ensure_dir(loom_accounts_dir());
  $store['schemaVersion']='1.0';$store['updatedAt']=server_timestamp();
  @file_put_contents(loom_accounts_file(),json_encode($store,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function loom_set_auth_cookie(string $token): void {
  $secure=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');
  setcookie(loom_auth_cookie_name(),$token,[
    'expires'=>time()+60*60*24*30,
    'path'=>'/',
    'secure'=>$secure,
    'httponly'=>true,
    'samesite'=>'Lax'
  ]);
}
function loom_clear_auth_cookie(): void {
  $secure=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');
  setcookie(loom_auth_cookie_name(),'',[
    'expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'
  ]);
}
function loom_auth_cookie_token(): string { return safe_token((string)($_COOKIE[loom_auth_cookie_name()]??'')); }

function loom_account_user_by_id(string $userId): ?array {
  if(loom_db_ready()){
    try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_users WHERE user_id=?");$st->execute([$userId]);$r=$st->fetch();if($r)return $r;}catch(Throwable $e){}
  }
  $s=loom_temp_account_store();return $s['users'][$userId]??null;
}
function loom_account_user_by_email(string $email): ?array {
  $norm=loom_email_norm($email);
  if(loom_db_ready()){
    try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_users WHERE email_norm=?");$st->execute([$norm]);$r=$st->fetch();if($r)return $r;}catch(Throwable $e){}
  }
  foreach((loom_temp_account_store()['users']??[]) as $u)if(($u['emailNorm']??'')===$norm)return $u;
  return null;
}
function loom_account_user_for_client(string $clientId): ?array {
  if(loom_db_ready()){
    try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT u.* FROM loom_user_clients c JOIN loom_users u ON u.user_id=c.user_id WHERE c.client_id=?");$st->execute([$clientId]);$r=$st->fetch();if($r)return $r;}catch(Throwable $e){}
  }
  $s=loom_temp_account_store();$uid=$s['clients'][$clientId]??null;return $uid?($s['users'][$uid]??null):null;
}
function loom_auth_user(): ?array {
  $token=loom_auth_cookie_token();if($token==='')return null;$hash=hash('sha256',$token);
  if(loom_db_ready()){
    try{
      $pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT u.*,s.client_id AS auth_client_id,s.expires_at FROM loom_auth_sessions s JOIN loom_users u ON u.user_id=s.user_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP(3)");
      $st->execute([$hash]);$r=$st->fetch();if($r){$pdo->prepare("UPDATE loom_auth_sessions SET last_seen=UTC_TIMESTAMP(3) WHERE token_hash=?")->execute([$hash]);return $r;}
    }catch(Throwable $e){}
  }
  $s=loom_temp_account_store();$sess=$s['sessions'][$hash]??null;
  if(!$sess||strtotime((string)($sess['expiresAt']??''))<time())return null;
  return $s['users'][$sess['userId']]??null;
}
function loom_issue_auth_session(string $userId,string $clientId): string {
  $token='auth_'.bin2hex(random_bytes(32));$hash=hash('sha256',$token);$now=server_timestamp();$exp=gmdate('c',time()+60*60*24*30);
  if(loom_db_ready()){
    $pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_auth_sessions(token_hash,user_id,client_id,created_at,expires_at,last_seen) VALUES(?,?,?,UTC_TIMESTAMP(3),DATE_ADD(UTC_TIMESTAMP(3),INTERVAL 30 DAY),UTC_TIMESTAMP(3))");
    $st->execute([$hash,$userId,$clientId]);
  }else{
    $s=loom_temp_account_store();$s['sessions'][$hash]=['userId'=>$userId,'clientId'=>$clientId,'createdAt'=>$now,'expiresAt'=>$exp];loom_write_temp_account_store($s);
  }
  loom_set_auth_cookie($token);return $token;
}
function loom_revoke_auth_session(): void {
  $token=loom_auth_cookie_token();if($token!==''){$hash=hash('sha256',$token);
    if(loom_db_ready()){try{$pdo=loom_db_pdo(true);$pdo->prepare("DELETE FROM loom_auth_sessions WHERE token_hash=?")->execute([$hash]);}catch(Throwable $e){}}
    else{$s=loom_temp_account_store();unset($s['sessions'][$hash]);loom_write_temp_account_store($s);}
  }
  loom_clear_auth_cookie();
}

function loom_username_owner(string $username): ?array {
  $norm=loom_username_norm($username);if($norm==='')return null;
  if(loom_db_ready()){
    try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_username_registry WHERE username_norm=?");$st->execute([$norm]);$r=$st->fetch();return $r?:null;}catch(Throwable $e){return null;}
  }
  $s=loom_temp_account_store();return $s['usernames'][$norm]??null;
}
function loom_reserve_username(string $username,string $ownerType,string $ownerId,?string $oldUsername=null): void {
  $username=loom_clean_username($username);$norm=loom_username_norm($username);
  if($username===''||$norm==='')throw new RuntimeException('Username cannot be empty.');
  if(!in_array($ownerType,['client','user'],true))throw new RuntimeException('Invalid username owner.');
  if(loom_db_ready()){
    $temp=loom_temp_account_store();$tempExisting=$temp['usernames'][$norm]??null;
    if($tempExisting && !(($tempExisting['ownerType']??'')===$ownerType && ($tempExisting['ownerId']??'')===$ownerId))throw new RuntimeException('That username is already taken.');
    $pdo=loom_db_pdo(true);$pdo->beginTransaction();
    try{
      $st=$pdo->prepare("SELECT owner_type,owner_id FROM loom_username_registry WHERE username_norm=? FOR UPDATE");$st->execute([$norm]);$r=$st->fetch();
      if($r && !($r['owner_type']===$ownerType && $r['owner_id']===$ownerId))throw new RuntimeException('That username is already taken.');
      if($oldUsername && loom_username_norm($oldUsername)!==$norm){
        $del=$pdo->prepare("DELETE FROM loom_username_registry WHERE username_norm=? AND owner_type=? AND owner_id=?");$del->execute([loom_username_norm($oldUsername),$ownerType,$ownerId]);
      }
      $up=$pdo->prepare("INSERT INTO loom_username_registry(username_norm,username,owner_type,owner_id,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE username=VALUES(username),owner_type=VALUES(owner_type),owner_id=VALUES(owner_id),updated_at=VALUES(updated_at)");
      $up->execute([$norm,$username,$ownerType,$ownerId]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return;
  }
  $s=loom_temp_account_store();$existing=$s['usernames'][$norm]??null;
  if($existing && !(($existing['ownerType']??'')===$ownerType && ($existing['ownerId']??'')===$ownerId))throw new RuntimeException('That username is already taken.');
  if($oldUsername && loom_username_norm($oldUsername)!==$norm){
    $old=loom_username_norm($oldUsername);$r=$s['usernames'][$old]??null;
    if($r && ($r['ownerType']??'')===$ownerType && ($r['ownerId']??'')===$ownerId)unset($s['usernames'][$old]);
  }
  $s['usernames'][$norm]=['username'=>$username,'ownerType'=>$ownerType,'ownerId'=>$ownerId,'updatedAt'=>server_timestamp()];loom_write_temp_account_store($s);
}
function loom_transfer_username_to_user(string $username,string $clientId,string $userId): void {
  $norm=loom_username_norm($username);
  if(loom_db_ready()){
    $temp=loom_temp_account_store();$tempExisting=$temp['usernames'][$norm]??null;
    if($tempExisting && !(($tempExisting['ownerType']??'')==='client'&&($tempExisting['ownerId']??'')===$clientId) && !(($tempExisting['ownerType']??'')==='user'&&($tempExisting['ownerId']??'')===$userId))throw new RuntimeException('That username is already taken.');
    $pdo=loom_db_pdo(true);$pdo->beginTransaction();
    try{
      $st=$pdo->prepare("SELECT * FROM loom_username_registry WHERE username_norm=? FOR UPDATE");$st->execute([$norm]);$r=$st->fetch();
      if($r && !(($r['owner_type']==='client'&&$r['owner_id']===$clientId)||($r['owner_type']==='user'&&$r['owner_id']===$userId)))throw new RuntimeException('That username is already taken.');
      $up=$pdo->prepare("INSERT INTO loom_username_registry(username_norm,username,owner_type,owner_id,updated_at) VALUES(?,?, 'user', ?, UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE username=VALUES(username),owner_type='user',owner_id=VALUES(owner_id),updated_at=VALUES(updated_at)");
      $up->execute([$norm,$username,$userId]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return;
  }
  $s=loom_temp_account_store();$r=$s['usernames'][$norm]??null;
  if($r && !(($r['ownerType']??'')==='client'&&($r['ownerId']??'')===$clientId) && !(($r['ownerType']??'')==='user'&&($r['ownerId']??'')===$userId))throw new RuntimeException('That username is already taken.');
  $s['usernames'][$norm]=['username'=>$username,'ownerType'=>'user','ownerId'=>$userId,'updatedAt'=>server_timestamp()];loom_write_temp_account_store($s);
}

function loom_bind_client_mapping_only(string $clientId,string $userId): void {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return;
  if(loom_db_ready()){
    $pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_user_clients(client_id,user_id,linked_at) VALUES(?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),linked_at=VALUES(linked_at)");$st->execute([$clientId,$userId]);
    try{$pdo->prepare("UPDATE loom_clients SET user_id=? WHERE client_id=?")->execute([$userId,$clientId]);}catch(Throwable $e){}
  }else{$s=loom_temp_account_store();$s['clients'][$clientId]=$userId;loom_write_temp_account_store($s);}
}
function loom_bind_client_to_user(string $clientId,string $userId): void {
  // v0.12.00: attaching a browser/device to a permanent account is a provenance-
  // preserving Guest History operation, not a destructive client->user migration.
  if(function_exists('loom_guest_attach_client_to_user')){loom_guest_attach_client_to_user($clientId,$userId,'self','',loom_auth_user()?'sign-in':'account-attach');return;}
  loom_bind_client_mapping_only($clientId,$userId);
  if(function_exists('loom_global_profile_promote_client'))loom_global_profile_promote_client($clientId,$userId);
  if(function_exists('loom_project_identity_promote_client'))loom_project_identity_promote_client($clientId,$userId);
  if(function_exists('loom_project_state_promote_client'))loom_project_state_promote_client($clientId,$userId);
  if(function_exists('loom_avatar_promote_client_to_user'))loom_avatar_promote_client_to_user($clientId,$userId);
  if(function_exists('loom_promote_client_moderation_to_user'))loom_promote_client_moderation_to_user($clientId,$userId);
}
function loom_create_account_record(string $email,string $password,string $privilege='User'): array {
  $email=trim($email);if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');if(strlen($password)<8)throw new RuntimeException('Password must be at least 8 characters.');if(loom_account_user_by_email($email))throw new RuntimeException('That email is already registered.');
  $userId='user_'.bin2hex(random_bytes(12));$accountHandle='acct_'.substr(hash('sha256',$userId),0,18);$now=server_timestamp();$hash=password_hash($password,PASSWORD_DEFAULT);
  if(loom_db_ready()){$pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_users(user_id,username,username_norm,email,email_norm,password_hash,privilege,created_at,updated_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP(3),UTC_TIMESTAMP(3))");$st->execute([$userId,$accountHandle,loom_username_norm($accountHandle),$email,loom_email_norm($email),$hash,$privilege]);}
  else{$store=loom_temp_account_store();$store['users'][$userId]=['userId'=>$userId,'username'=>$accountHandle,'usernameNorm'=>loom_username_norm($accountHandle),'email'=>$email,'emailNorm'=>loom_email_norm($email),'passwordHash'=>$hash,'privilege'=>$privilege,'createdAt'=>$now,'updatedAt'=>$now];loom_write_temp_account_store($store);}
  return loom_account_user_by_id($userId)?:['user_id'=>$userId,'username'=>$accountHandle,'email'=>$email,'privilege'=>$privilege];
}
function loom_register_account(string $clientId,string $projectUsername,string $email,string $password,string $privilege='User'): array {
  $projectUsername=loom_clean_username($projectUsername);$email=trim($email);
  if($projectUsername==='')throw new RuntimeException('Set a username for this project before creating a permanent account.');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
  if(strlen($password)<8)throw new RuntimeException('Password must be at least 8 characters.');
  if(loom_account_user_by_email($email))throw new RuntimeException('That email is already registered.');
  $user=loom_create_account_record($email,$password,$privilege);$userId=(string)($user['user_id']??$user['userId']??'');
  loom_bind_client_to_user($clientId,$userId);loom_issue_auth_session($userId,$clientId);
  return loom_account_user_by_id($userId)?:$user;
}
function loom_replace_account_password(string $userId,string $password,string $issueClientId=''): void {
  $userId=safe_token($userId);if($userId==='')throw new RuntimeException('Invalid account.');if(strlen($password)<8)throw new RuntimeException('Password must be at least 8 characters.');$hash=password_hash($password,PASSWORD_DEFAULT);
  if(loom_db_ready()){$pdo=loom_db_pdo(true);$pdo->prepare("UPDATE loom_users SET password_hash=?,updated_at=UTC_TIMESTAMP(3) WHERE user_id=?")->execute([$hash,$userId]);$pdo->prepare("DELETE FROM loom_auth_sessions WHERE user_id=?")->execute([$userId]);}
  else{$s=loom_temp_account_store();if(!isset($s['users'][$userId]))throw new RuntimeException('User account not found.');$s['users'][$userId]['passwordHash']=$hash;$s['users'][$userId]['updatedAt']=server_timestamp();foreach(($s['sessions']??[]) as $k=>$sess)if(($sess['userId']??'')===$userId)unset($s['sessions'][$k]);loom_write_temp_account_store($s);}
  if($issueClientId!=='')loom_issue_auth_session($userId,$issueClientId);$u=loom_account_user_by_id($userId);if($u&&function_exists('loom_email_notify_user'))try{loom_email_notify_user('passwordChanged',$u);}catch(Throwable $e){}
}
function loom_login_account(string $clientId,string $email,string $password): array {
  $u=loom_account_user_by_email($email);if(!$u)throw new RuntimeException('Email or password is incorrect.');
  $hash=(string)($u['password_hash']??$u['passwordHash']??'');if($hash===''||!password_verify($password,$hash))throw new RuntimeException('Email or password is incorrect.');
  $userId=(string)($u['user_id']??$u['userId']??'');if($userId==='')throw new RuntimeException('Invalid account.');
  loom_bind_client_to_user($clientId,$userId);loom_issue_auth_session($userId,$clientId);return $u;
}
function loom_update_account_username(array $user,string $newUsername): void {
  $uid=(string)($user['user_id']??$user['userId']??'');$old=(string)($user['username']??'');$newUsername=loom_clean_username($newUsername);
  loom_reserve_username($newUsername,'user',$uid,$old);
  if(loom_db_ready()){
    $pdo=loom_db_pdo(true);$st=$pdo->prepare("UPDATE loom_users SET username=?,username_norm=?,updated_at=UTC_TIMESTAMP(3) WHERE user_id=?");$st->execute([$newUsername,loom_username_norm($newUsername),$uid]);
    try{$st=$pdo->prepare("UPDATE loom_client_profiles p JOIN loom_user_clients c ON c.client_id=p.client_id SET p.username=?,p.username_norm=?,p.updated_at=UTC_TIMESTAMP(3) WHERE c.user_id=?");$st->execute([$newUsername,loom_username_norm($newUsername),$uid]);}catch(Throwable $e){}
  }else{
    $s=loom_temp_account_store();if(isset($s['users'][$uid])){$s['users'][$uid]['username']=$newUsername;$s['users'][$uid]['usernameNorm']=loom_username_norm($newUsername);$s['users'][$uid]['updatedAt']=server_timestamp();}
    $clients=[];foreach(($s['clients']??[]) as $cid=>$mapped)if($mapped===$uid)$clients[]=$cid;loom_write_temp_account_store($s);
    foreach(glob(loom_data_dir().'/users/*.json')?:[] as $file){$p=read_json_file($file);if(!$p||!in_array((string)($p['clientId']??''),$clients,true))continue;$p['username']=$newUsername;$p['updatedAt']=server_timestamp();@file_put_contents($file,json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);}
  }
}
function loom_link_admin_to_user_if_applicable(string $clientId,string $userId): void {
  $state=loom_admin_identity();if(!$state)return;
  if(($state['clientId']??'')===$clientId){
    $state['userId']=$userId;loom_write_admin_identity($state);
    if(loom_db_ready()){try{$pdo=loom_db_pdo(true);$pdo->prepare("UPDATE loom_users SET privilege='Admin' WHERE user_id=?")->execute([$userId]);}catch(Throwable $e){}}
    else{$s=loom_temp_account_store();if(isset($s['users'][$userId])){$s['users'][$userId]['privilege']='Admin';loom_write_temp_account_store($s);}}
  }
}
function loom_reconcile_admin_identity(string $clientId): void {
  $clientId=safe_token($clientId);$auth=loom_auth_user();$state=loom_admin_identity();
  if(!$auth)return;
  $uid=(string)($auth['user_id']??$auth['userId']??'');$storedPrivilege=(string)($auth['privilege']??'User');
  if($uid==='')return;

  // v0.15.18: admin state is the immutable System Owner pointer, not a
  // "last Admin who logged in" pointer. Delegated LOOM Admins must never
  // replace/demote/lock out the original owner.
  if(strcasecmp($storedPrivilege,'Admin')===0){
    if(!$state){
      // Recovery only for legacy installs whose durable Admin exists but whose
      // owner state was lost. This cannot run when an owner state already exists.
      $state=['schemaVersion'=>'1.0','clientId'=>$clientId,'userId'=>$uid,'tokenHash'=>null,'createdAt'=>server_timestamp(),'createdEpochMs'=>server_epoch_ms(),'bootstrapMethod'=>'permanent-admin-account-recovery'];
      loom_write_admin_identity($state);
    }else{
      $ownerUid=(string)($state['userId']??'');$ownerClient=(string)($state['clientId']??'');
      if($ownerUid!==''&&!hash_equals($ownerUid,$uid)){
        // This is a delegated/legacy secondary LOOM Admin. Preserve owner state.
        loom_bind_client_to_user($clientId,$uid);return;
      }
      $changed=false;
      if($ownerUid===''&&$ownerClient!==''&&hash_equals($ownerClient,$clientId)&&loom_admin_cookie_valid()){$state['userId']=$uid;$changed=true;}
      if(empty($state['clientId'])){$state['clientId']=$clientId;$changed=true;}
      if(empty($state['createdAt'])){$state['createdAt']=server_timestamp();$changed=true;}
      if(empty($state['bootstrapMethod'])){$state['bootstrapMethod']='permanent-admin-account-recovery';$changed=true;}
      if($changed)loom_write_admin_identity($state);
    }
    loom_bind_client_to_user($clientId,$uid);return;
  }

  // Legacy one-time owner promotion: the original bootstrap browser can link
  // its newly created permanent account only while presenting its secret cookie.
  if(!$state||!loom_admin_cookie_valid())return;$stateClient=(string)($state['clientId']??'');
  if($stateClient===''||!hash_equals($stateClient,$clientId))return;
  $stateUid=(string)($state['userId']??'');if($stateUid!==''&&!hash_equals($stateUid,$uid))return;
  $state['userId']=$uid;loom_write_admin_identity($state);
  if(loom_db_ready()){try{$pdo=loom_db_pdo(true);$pdo->prepare("UPDATE loom_users SET privilege='Admin' WHERE user_id=?")->execute([$uid]);}catch(Throwable $e){}}
  else{$store=loom_temp_account_store();if(isset($store['users'][$uid])){$store['users'][$uid]['privilege']='Admin';loom_write_temp_account_store($store);}}
  loom_bind_client_to_user($clientId,$uid);
}

function loom_account_public_status(string $clientId,string $project=''): array {
  loom_reconcile_admin_identity($clientId);$auth=loom_auth_user();$linked=loom_account_user_for_client($clientId);$u=$auth?:$linked;$storedPrivilege=(string)($u['privilege']??'User');$effectiveAdmin=loom_client_is_admin($clientId);$source='user';
  if($effectiveAdmin){if($auth&&strcasecmp((string)($auth['privilege']??'User'),'Admin')===0)$source='permanent-admin-account';elseif(loom_admin_cookie_valid())$source='bootstrap-admin-recovery';else $source='linked-admin-state';}
  $global=null;try{$global=loom_global_profile_ensure($clientId);}catch(Throwable $e){} $identity=null;if($project!==''&&project_dir($project)){try{$identity=loom_project_identity_ensure($project,$clientId,true);}catch(Throwable $e){}}
  return ['authenticated'=>(bool)$auth,'registered'=>(bool)$u,'userId'=>$u['user_id']??$u['userId']??null,'username'=>$identity['effectiveUsername']??$global['username']??null,'projectUsername'=>$identity['effectiveUsername']??null,'globalUsername'=>$global['username']??null,'accountHandle'=>$u['username']??null,'email'=>$u['email']??null,'privilege'=>$effectiveAdmin?'Admin':'User','accountPrivilege'=>$storedPrivilege,'effectivePrivilege'=>$effectiveAdmin?'Admin':'User','authorizationSource'=>$source,'privilegeMismatch'=>strcasecmp($storedPrivilege,$effectiveAdmin?'Admin':'User')!==0,'storageMode'=>loom_db_ready()?'database':'durable-local'];
}
function loom_request_is_admin(): bool {
  $auth=loom_auth_user();
  $authId=(string)($auth['user_id']??$auth['userId']??'');
  $authPrivilege=(string)($auth['privilege']??'User');

  // Durable signed-in account privilege is authoritative for LOOM Admins.
  if($authId!=='' && strcasecmp($authPrivilege,'Admin')===0)return true;
  $candidate=(string)($_REQUEST['clientId']??'');
  if($candidate!==''&&function_exists('loom_access_client_is_loom_admin')&&loom_access_client_is_loom_admin($candidate))return true;

  $state=loom_admin_identity();if(!$state)return false;
  $uid=(string)($state['userId']??'');

  // Compatibility for already-linked promoted accounts.
  if($uid!=='' && $authId!=='' && hash_equals($uid,$authId))return true;

  // Browser navigation bridge issued only after canonical Admin status succeeds.
  if(function_exists('loom_admin_navigation_cookie_valid')&&loom_admin_navigation_cookie_valid())return true;

  // Bootstrap-browser recovery path.
  if(loom_admin_cookie_valid()){
    if($uid==='' || $authId==='' || hash_equals($uid,$authId))return true;
  }
  return false;
}
