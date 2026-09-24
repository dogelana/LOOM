<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
declare(strict_types=1);

function loom_integrity_backup_root(): string { return loom_data_dir().'/admin/database-backups'; }
function loom_integrity_tables(): array {
  return [
    'loom_meta','loom_clients','loom_client_profiles','loom_users','loom_user_clients',
    'loom_username_registry','loom_global_profiles','loom_project_identities','loom_project_module_state','loom_user_avatar_profiles','loom_auth_sessions','loom_admin_state',
    'loom_module_settings','loom_sessions','loom_events','loom_identity_ips',
    'loom_project_user_state','loom_admin_audit','loom_guest_identities','loom_guest_clients',
    'loom_identity_attachments','loom_guest_recovery','loom_identity_merge_conflicts','loom_identity_audit'
  ];
}
function loom_integrity_issue(array &$issues,string $severity,string $code,string $message,bool $repairable=false,array $details=[]): void {
  $issues[]=['severity'=>$severity,'code'=>$code,'message'=>$message,'repairable'=>$repairable,'details'=>$details];
}
function loom_db_table_exists(PDO $pdo,string $table): bool {
  if(!preg_match('/^loom_[a-z0-9_]+$/',$table))return false;
  try{$st=$pdo->query("SHOW TABLES LIKE ".$pdo->quote($table));return (bool)$st->fetchColumn();}catch(Throwable $e){return false;}
}
function loom_db_integrity_backup(): array {
  $pdo=loom_db_pdo(true);if(!$pdo)throw new RuntimeException('SQL is not initialized or reachable.');
  $id=gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
  $dir=loom_integrity_backup_root().'/'.$id;ensure_dir($dir);
  @file_put_contents(loom_integrity_backup_root().'/.htaccess',"Require all denied\n",LOCK_EX);
  $counts=[];$errors=[];
  foreach(loom_integrity_tables() as $table){
    if(!loom_db_table_exists($pdo,$table)){continue;}
    $file=$dir.'/'.$table.'.jsonl';$fh=@fopen($file,'wb');if(!$fh){$errors[$table]='backup-file-open-failed';continue;}
    $count=0;
    try{
      $st=$pdo->query("SELECT * FROM `{$table}`");
      while($row=$st->fetch(PDO::FETCH_ASSOC)){fwrite($fh,json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");$count++;}
      $counts[$table]=$count;
    }catch(Throwable $e){$errors[$table]=$e->getMessage();}
    fclose($fh);
  }
  $manifest=[
    'backupId'=>$id,'createdAt'=>server_timestamp(),'database'=>array_diff_key(loom_db_public_config(),['username'=>true]),
    'tables'=>$counts,'errors'=>$errors,'format'=>'one JSON object per line, one file per table'
  ];
  @file_put_contents($dir.'/manifest.json',json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  return $manifest;
}
function loom_integrity_sql_user(PDO $pdo,string $userId): ?array {
  if($userId==='')return null;
  $st=$pdo->prepare("SELECT * FROM loom_users WHERE user_id=?");$st->execute([$userId]);$r=$st->fetch();return $r?:null;
}
function loom_integrity_temp_user(string $userId,array $auth=[]): ?array {
  $store=loom_temp_account_store();$u=$store['users'][$userId]??null;
  if(is_array($u))return $u;
  $authId=(string)($auth['user_id']??$auth['userId']??'');
  return ($authId!==''&&hash_equals($authId,$userId))?$auth:null;
}
function loom_integrity_user_fields(array $u,string $fallbackPrivilege='User'): array {
  $username=loom_clean_username((string)($u['username']??''));
  $email=trim((string)($u['email']??''));
  $passwordHash=(string)($u['password_hash']??$u['passwordHash']??'');
  $privilege=(string)($u['privilege']??$fallbackPrivilege);
  if(!in_array($privilege,['Admin','User'],true))$privilege=$fallbackPrivilege;
  $created=(string)($u['created_at']??$u['createdAt']??server_timestamp());
  $updated=(string)($u['updated_at']??$u['updatedAt']??server_timestamp());
  if($username===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$passwordHash===''){
    throw new RuntimeException('The signed-in temporary account is missing username, email, or password-hash data. Repair stopped rather than inventing account data.');
  }
  return [
    'username'=>$username,'usernameNorm'=>loom_username_norm($username),
    'email'=>$email,'emailNorm'=>loom_email_norm($email),'passwordHash'=>$passwordHash,
    'privilege'=>$privilege,'createdAt'=>$created,'updatedAt'=>$updated
  ];
}
function loom_integrity_ensure_current_user(PDO $pdo,string $requestedUserId,array $auth,array &$actions): array {
  $existing=loom_integrity_sql_user($pdo,$requestedUserId);
  if($existing)return ['userId'=>$requestedUserId,'row'=>$existing,'created'=>false,'adopted'=>false];

  $source=loom_integrity_temp_user($requestedUserId,$auth);
  if(!$source)throw new RuntimeException('The signed-in user is missing from loom_users and no matching temporary account record exists. Repair stopped rather than guessing.');
  $f=loom_integrity_user_fields($source,(string)($auth['privilege']??'User'));

  // If migration already created the same logical account under another user_id,
  // adopt that SQL row only when BOTH unique identity fields match exactly.
  $st=$pdo->prepare("SELECT * FROM loom_users WHERE username_norm=? OR email_norm=? ORDER BY user_id");
  $st->execute([$f['usernameNorm'],$f['emailNorm']]);$candidates=$st->fetchAll();
  if($candidates){
    $exact=array_values(array_filter($candidates,fn($r)=>
      (string)$r['username_norm']===$f['usernameNorm'] && (string)$r['email_norm']===$f['emailNorm']
    ));
    if(count($exact)===1){
      $row=$exact[0];$uid=(string)$row['user_id'];
      if(strcasecmp($f['privilege'],'Admin')===0 && strcasecmp((string)$row['privilege'],'Admin')!==0){
        $pdo->prepare("UPDATE loom_users SET privilege='Admin',updated_at=UTC_TIMESTAMP(3) WHERE user_id=?")->execute([$uid]);
        $row['privilege']='Admin';$actions[]='restored-admin-privilege-on-matching-sql-account';
      }
      $actions[]='adopted-existing-sql-account-for-signed-in-identity';
      return ['userId'=>$uid,'row'=>$row,'created'=>false,'adopted'=>true,'previousUserId'=>$requestedUserId];
    }
    throw new RuntimeException('A SQL user already conflicts with the signed-in account username or email, but the records are not an exact username+email match. Repair stopped because this merge is ambiguous.');
  }

  $st=$pdo->prepare("INSERT INTO loom_users(user_id,username,username_norm,email,email_norm,password_hash,privilege,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)");
  $st->execute([
    $requestedUserId,$f['username'],$f['usernameNorm'],$f['email'],$f['emailNorm'],$f['passwordHash'],$f['privilege'],
    loom_db_dt($f['createdAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($f['updatedAt'])?:gmdate('Y-m-d H:i:s.000')
  ]);
  $actions[]='restored-missing-signed-in-user-row-before-child-links';
  return ['userId'=>$requestedUserId,'row'=>loom_integrity_sql_user($pdo,$requestedUserId),'created'=>true,'adopted'=>false];
}
function loom_integrity_migrate_current_auth_session(PDO $pdo,string $userId,string $clientId,array &$actions): void {
  $token=loom_auth_cookie_token();if($token==='')return;$hash=hash('sha256',$token);
  $st=$pdo->prepare("SELECT token_hash FROM loom_auth_sessions WHERE token_hash=?");$st->execute([$hash]);if($st->fetchColumn())return;
  $store=loom_temp_account_store();$sess=$store['sessions'][$hash]??null;if(!is_array($sess))return;
  $created=loom_db_dt((string)($sess['createdAt']??server_timestamp()))?:gmdate('Y-m-d H:i:s.000');
  $expires=loom_db_dt((string)($sess['expiresAt']??gmdate('c',time()+2592000)))?:gmdate('Y-m-d H:i:s.000',time()+2592000);
  $st=$pdo->prepare("INSERT INTO loom_auth_sessions(token_hash,user_id,client_id,created_at,expires_at,last_seen) VALUES(?,?,?,?,?,UTC_TIMESTAMP(3))");
  $st->execute([$hash,$userId,$clientId,$created,$expires]);$actions[]='migrated-current-auth-session-to-sql';
}

function loom_db_integrity_scan(string $clientId=''): array {
  $status=loom_db_status();$issues=[];$counts=[];
  if(empty($status['connected'])||empty($status['initialized'])){
    loom_integrity_issue($issues,'critical','database-unavailable','SQL is not connected and initialized.',false);
    return ['ok'=>false,'healthy'=>false,'database'=>$status,'issues'=>$issues,'counts'=>$counts];
  }
  $pdo=loom_db_pdo(true);if(!$pdo){loom_integrity_issue($issues,'critical','database-unavailable','LOOM could not open the initialized SQL database.',false);return ['ok'=>false,'healthy'=>false,'database'=>$status,'issues'=>$issues,'counts'=>$counts];}

  foreach(loom_integrity_tables() as $table){
    if(!loom_db_table_exists($pdo,$table)){loom_integrity_issue($issues,'critical','missing-table','Required LOOM table is missing: '.$table,false,['table'=>$table]);continue;}
    try{$counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();}catch(Throwable $e){$counts[$table]=null;loom_integrity_issue($issues,'critical','table-read-failed','Could not read '.$table,false,['table'=>$table,'error'=>$e->getMessage()]);}
  }

  $auth=loom_auth_user();$authId=(string)($auth['user_id']??$auth['userId']??'');$authPrivilege=(string)($auth['privilege']??'User');
  $state=loom_admin_identity();$stateUid=(string)($state['userId']??'');$stateClient=(string)($state['clientId']??'');
  $adminCount=0;try{$adminCount=(int)$pdo->query("SELECT COUNT(*) FROM loom_users WHERE privilege='Admin'")->fetchColumn();}catch(Throwable $e){}
  if($adminCount===0)loom_integrity_issue($issues,'critical','no-admin-account','No permanent account is marked Admin.',true);
  if($adminCount>1)loom_integrity_issue($issues,'warning','multiple-admin-accounts','More than one permanent account is marked Admin. LOOM supports this, but review whether it is intentional.',false,['count'=>$adminCount]);

  if($stateUid!==''){
    $st=$pdo->prepare("SELECT user_id,privilege,username,email FROM loom_users WHERE user_id=?");$st->execute([$stateUid]);$u=$st->fetch();
    if(!$u)loom_integrity_issue($issues,'critical','admin-state-orphan','Admin state points to a user_id that does not exist.',true,['userId'=>$stateUid]);
    elseif(strcasecmp((string)$u['privilege'],'Admin')!==0)loom_integrity_issue($issues,'critical','admin-state-privilege-mismatch','Admin state points to an account whose durable privilege is not Admin.',true,['userId'=>$stateUid,'username'=>$u['username']]);
  }else{
    loom_integrity_issue($issues,'warning','admin-state-user-missing','Admin state has no permanent user_id association.',true);
  }

  if($authId!==''){
    $sqlAuthUser=loom_integrity_sql_user($pdo,$authId);
    if(!$sqlAuthUser)loom_integrity_issue($issues,'critical','current-auth-user-missing-from-sql','The signed-in permanent account exists in the temporary account store/session but its parent row is missing from loom_users. Child identity mappings must not be repaired until this parent row is restored.',true,['signedInUserId'=>$authId]);
    if(strcasecmp($authPrivilege,'Admin')===0 && $stateUid!==$authId){
      loom_integrity_issue($issues,'critical','current-admin-state-mismatch','The signed-in account is Admin, but admin state points somewhere else or nowhere.',true,['signedInUserId'=>$authId,'adminStateUserId'=>$stateUid?:null]);
    }
    $st=$pdo->prepare("SELECT user_id FROM loom_user_clients WHERE client_id=?");$st->execute([$clientId]);$mapped=(string)($st->fetchColumn()?:'');
    if($mapped!==$authId)loom_integrity_issue($issues,'critical','current-client-link-mismatch','The current browser client is not linked to the signed-in user in loom_user_clients.',true,['clientId'=>$clientId,'expectedUserId'=>$authId,'actualUserId'=>$mapped?:null]);
    $st=$pdo->prepare("SELECT user_id FROM loom_clients WHERE client_id=?");$st->execute([$clientId]);$clientUser=(string)($st->fetchColumn()?:'');
    if($clientUser!==''&&$clientUser!==$authId)loom_integrity_issue($issues,'warning','client-cache-user-mismatch','loom_clients contains a different user_id than the authenticated account.',true,['clientId'=>$clientId,'expectedUserId'=>$authId,'actualUserId'=>$clientUser]);
  }

  try{
    $n=(int)$pdo->query("SELECT COUNT(*) FROM loom_user_clients uc LEFT JOIN loom_users u ON u.user_id=uc.user_id WHERE u.user_id IS NULL")->fetchColumn();
    if($n>0)loom_integrity_issue($issues,'critical','orphan-user-client-links','Some client→user links reference missing users.',false,['count'=>$n]);
  }catch(Throwable $e){}
  try{
    $n=(int)$pdo->query("SELECT COUNT(*) FROM loom_auth_sessions s LEFT JOIN loom_users u ON u.user_id=s.user_id WHERE u.user_id IS NULL")->fetchColumn();
    if($n>0)loom_integrity_issue($issues,'critical','orphan-auth-sessions','Some authentication sessions reference missing users.',false,['count'=>$n]);
  }catch(Throwable $e){}
  try{
    $n=(int)$pdo->query("SELECT COUNT(*) FROM loom_clients c JOIN loom_user_clients uc ON uc.client_id=c.client_id WHERE c.user_id IS NULL OR c.user_id<>uc.user_id")->fetchColumn();
    if($n>0)loom_integrity_issue($issues,'warning','client-link-cache-mismatches','Some loom_clients.user_id cache values disagree with loom_user_clients.',true,['count'=>$n]);
  }catch(Throwable $e){}
  try{
    $n=(int)$pdo->query("SELECT COUNT(*) FROM loom_auth_sessions WHERE expires_at<=UTC_TIMESTAMP(3)")->fetchColumn();
    if($n>0)loom_integrity_issue($issues,'info','expired-auth-sessions','Expired login sessions can be safely cleaned up.',true,['count'=>$n]);
  }catch(Throwable $e){}

  if(function_exists('loom_project_identity_db_table'))loom_project_identity_db_table();
  try{$n=(int)$pdo->query("SELECT COUNT(*) FROM loom_project_identities pi LEFT JOIN loom_users u ON pi.owner_type='user' AND u.user_id=pi.owner_id WHERE pi.owner_type='user' AND u.user_id IS NULL")->fetchColumn();if($n>0)loom_integrity_issue($issues,'warning','orphan-project-identities','Some project identities point to missing permanent users.',false,['count'=>$n]);}catch(Throwable $e){}

  $critical=count(array_filter($issues,fn($i)=>$i['severity']==='critical'));
  $warnings=count(array_filter($issues,fn($i)=>$i['severity']==='warning'));
  return [
    'ok'=>true,'healthy'=>$critical===0,'database'=>$status,'counts'=>$counts,'issues'=>$issues,
    'summary'=>['critical'=>$critical,'warnings'=>$warnings,'info'=>count($issues)-$critical-$warnings],
    'current'=>[
      'clientId'=>$clientId?:null,'authenticatedUserId'=>$authId?:null,'authenticatedPrivilege'=>$authId!==''?$authPrivilege:null,
      'adminStateUserId'=>$stateUid?:null,'adminStateClientId'=>$stateClient?:null,'permanentAdminAccounts'=>$adminCount
    ]
  ];
}
function loom_db_integrity_repair(string $clientId): array {
  $clientId=safe_token($clientId);$pdo=loom_db_pdo(true);if(!$pdo)throw new RuntimeException('SQL is not initialized or reachable.');
  $auth=loom_auth_user();if(!$auth)throw new RuntimeException('Sign in before repairing identity mappings.');
  $requestedUid=(string)($auth['user_id']??$auth['userId']??'');$priv=(string)($auth['privilege']??'User');
  if($requestedUid===''||strcasecmp($priv,'Admin')!==0)throw new RuntimeException('The signed-in permanent account is not marked Admin in the active account record. Repair stopped rather than guessing.');

  // Always snapshot before the first SQL mutation.
  $backup=loom_db_integrity_backup();$actions=[];$stateToPersist=null;$effectiveUid=$requestedUid;
  $pdo->beginTransaction();
  try{
    // PARENT FIRST: a child row in loom_user_clients/loom_auth_sessions is forbidden
    // until loom_users contains the referenced user_id. v0.11.13 did this backwards.
    $ensured=loom_integrity_ensure_current_user($pdo,$requestedUid,$auth,$actions);
    $effectiveUid=(string)$ensured['userId'];

    // If an earlier migration created the same logical account under a different
    // user_id, make the current cookie a SQL session for that adopted account.
    loom_integrity_migrate_current_auth_session($pdo,$effectiveUid,$clientId,$actions);

    $username=(string)($ensured['row']['username']??$auth['username']??'');

    // CHILD LINKS only after the parent user is guaranteed to exist.
    $pdo->prepare("INSERT INTO loom_user_clients(client_id,user_id,linked_at) VALUES(?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),linked_at=VALUES(linked_at)")->execute([$clientId,$effectiveUid]);
    $actions[]='bound-current-client-to-admin-user';
    $pdo->prepare("UPDATE loom_clients SET user_id=? WHERE client_id=?")->execute([$effectiveUid,$clientId]);
    $actions[]='aligned-client-user-cache';

    $state=loom_admin_identity()?:[];
    $state['schemaVersion']=$state['schemaVersion']??'1.0';$state['userId']=$effectiveUid;
    if(empty($state['clientId']))$state['clientId']=$clientId;
    if(empty($state['createdAt']))$state['createdAt']=server_timestamp();
    if(empty($state['bootstrapMethod']))$state['bootstrapMethod']='integrity-repair';
    $st=$pdo->prepare("INSERT INTO loom_admin_state(state_id,client_id,user_id,token_hash,created_at,bootstrap_method) VALUES(1,?,?,?,?,?) ON DUPLICATE KEY UPDATE client_id=VALUES(client_id),user_id=VALUES(user_id),token_hash=VALUES(token_hash),bootstrap_method=VALUES(bootstrap_method)");
    $st->execute([$state['clientId']??null,$effectiveUid,$state['tokenHash']??null,loom_db_dt($state['createdAt']),$state['bootstrapMethod']??'integrity-repair']);
    $actions[]='reconciled-admin-state';$stateToPersist=$state;

    $n=$pdo->exec("UPDATE loom_clients c JOIN loom_user_clients uc ON uc.client_id=c.client_id SET c.user_id=uc.user_id WHERE c.user_id IS NULL OR c.user_id<>uc.user_id");
    if($n>0)$actions[]='aligned-'.$n.'-client-cache-links';
    $n=$pdo->exec("DELETE FROM loom_auth_sessions WHERE expires_at<=UTC_TIMESTAMP(3)");
    if($n>0)$actions[]='removed-'.$n.'-expired-auth-sessions';
    $pdo->commit();

    if($stateToPersist)loom_write_admin_identity($stateToPersist);

    // Keep the temporary safety store aligned if SQL adopted an already-existing
    // user_id for the exact same username+email identity.
    if($effectiveUid!==$requestedUid){
      $store=loom_temp_account_store();$old=$store['users'][$requestedUid]??null;
      if(is_array($old)){
        $old['userId']=$effectiveUid;$old['privilege']='Admin';$store['users'][$effectiveUid]=$old;unset($store['users'][$requestedUid]);
        foreach(($store['clients']??[]) as $cid=>$mapped)if((string)$mapped===$requestedUid)$store['clients'][$cid]=$effectiveUid;
        foreach(($store['sessions']??[]) as $hash=>$sess)if((string)($sess['userId']??'')===$requestedUid)$store['sessions'][$hash]['userId']=$effectiveUid;
        foreach(($store['usernames']??[]) as $key=>$owner)if(($owner['ownerType']??'')==='user'&&(string)($owner['ownerId']??'')===$requestedUid)$store['usernames'][$key]['ownerId']=$effectiveUid;
        loom_write_temp_account_store($store);$actions[]='aligned-temporary-account-id-with-adopted-sql-user';
      }
    }

    $profile=loom_db_read_client_profile($clientId)?:[];
    $profile['profileId']=$profile['profileId']??('profile_'.substr(hash('sha256',$clientId),0,16));
    $profile['clientId']=$clientId;$profile['userId']=$effectiveUid;$profile['username']=$username?:($profile['username']??null);
    $profile['createdAt']=$profile['createdAt']??server_timestamp();$profile['updatedAt']=server_timestamp();
    loom_db_write_client_profile($profile);
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

  return ['backup'=>$backup,'actions'=>$actions,'effectiveUserId'=>$effectiveUid,'scan'=>loom_db_integrity_scan($clientId)];
}
