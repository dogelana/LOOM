<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
declare(strict_types=1);

// LOOM v0.12.00 — Guest Identity Permanence & Recovery
// A Guest Identity is durable. Authentication can attach it to a permanent user,
// but source history/provenance is retained and never silently deleted.

function loom_identity_dir(): string { $d=loom_data_dir().'/identity';ensure_dir($d);return $d; }
function loom_guest_store_file(): string { return loom_identity_dir().'/guest-identities.json'; }
function loom_identity_audit_file(): string { return loom_identity_dir().'/audit.jsonl'; }
function loom_guest_snapshot_dir(string $guestId): string { $d=loom_identity_dir().'/guest-snapshots/'.safe_token($guestId);ensure_dir($d);return $d; }
function loom_guest_asset_dir(string $guestId): string { $d=loom_identity_dir().'/guest-assets/'.safe_token($guestId);ensure_dir($d);return $d; }
function loom_guest_store(): array {
  $s=read_json_file(loom_guest_store_file())?:[];
  return array_replace(['schemaVersion'=>'1.0','guests'=>[],'clientMap'=>[],'attachments'=>[],'recoveryIndex'=>[],'conflicts'=>[]],$s);
}
function loom_guest_write_store(array $s): void {
  ensure_dir(loom_identity_dir());$s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();
  @file_put_contents(loom_guest_store_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function loom_identity_audit(string $event,array $payload=[]): array {
  $row=['id'=>event_id('identity'),'event'=>$event,'createdAt'=>server_timestamp(),'payload'=>$payload];
  @file_put_contents(loom_identity_audit_file(),json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
  if(loom_db_ready())try{
    loom_guest_db_tables();$pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_identity_audit(event_id,event_type,created_at,payload_json) VALUES(?,?,UTC_TIMESTAMP(3),?)");$st->execute([$row['id'],$event,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  }catch(Throwable $e){}
  return $row;
}

function loom_guest_db_tables(): void {
  if(!loom_db_ready())return;static $done=false;if($done)return;
  try{
    $pdo=loom_db_pdo(true);
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_guest_identities (guest_id VARCHAR(96) PRIMARY KEY,status VARCHAR(32) NOT NULL,primary_client_id VARCHAR(96) NOT NULL,attached_user_id VARCHAR(96) NULL,merged_into_guest_id VARCHAR(96) NULL,created_at DATETIME(3) NOT NULL,updated_at DATETIME(3) NOT NULL,attached_at DATETIME(3) NULL,recovery_hint VARCHAR(32) NULL,payload_json LONGTEXT NULL,INDEX idx_guest_status(status),INDEX idx_guest_user(attached_user_id),INDEX idx_guest_updated(updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_guest_clients (client_id VARCHAR(96) PRIMARY KEY,guest_id VARCHAR(96) NOT NULL,first_seen DATETIME(3) NOT NULL,last_seen DATETIME(3) NOT NULL,INDEX idx_guest_clients_guest(guest_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_identity_attachments (attachment_id VARCHAR(96) PRIMARY KEY,guest_id VARCHAR(96) NOT NULL,user_id VARCHAR(96) NOT NULL,source_client_id VARCHAR(96) NULL,actor_type VARCHAR(32) NOT NULL,actor_id VARCHAR(96) NULL,mode VARCHAR(48) NOT NULL,attached_at DATETIME(3) NOT NULL,summary_json LONGTEXT NULL,INDEX idx_attach_guest(guest_id),INDEX idx_attach_user(user_id),INDEX idx_attach_time(attached_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_guest_recovery (recovery_hash CHAR(64) PRIMARY KEY,guest_id VARCHAR(96) NOT NULL,created_at DATETIME(3) NOT NULL,last_used_at DATETIME(3) NULL,revoked_at DATETIME(3) NULL,INDEX idx_guest_recovery_guest(guest_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_identity_merge_conflicts (conflict_id VARCHAR(96) PRIMARY KEY,guest_id VARCHAR(96) NULL,user_id VARCHAR(96) NULL,project_slug VARCHAR(96) NULL,module_id VARCHAR(160) NULL,path_text VARCHAR(512) NULL,guest_value_json LONGTEXT NULL,user_value_json LONGTEXT NULL,resolution VARCHAR(64) NOT NULL,created_at DATETIME(3) NOT NULL,resolved_at DATETIME(3) NULL,INDEX idx_merge_guest(guest_id),INDEX idx_merge_user(user_id),INDEX idx_merge_project(project_slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS loom_identity_audit (event_id VARCHAR(96) PRIMARY KEY,event_type VARCHAR(96) NOT NULL,created_at DATETIME(3) NOT NULL,payload_json LONGTEXT NULL,INDEX idx_identity_audit_type(event_type),INDEX idx_identity_audit_time(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done=true;
  }catch(Throwable $e){}
}
function loom_guest_db_sync(array $g): void {
  loom_guest_db_tables();if(!loom_db_ready())return;try{
    $pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_guest_identities(guest_id,status,primary_client_id,attached_user_id,merged_into_guest_id,created_at,updated_at,attached_at,recovery_hint,payload_json) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),primary_client_id=VALUES(primary_client_id),attached_user_id=VALUES(attached_user_id),merged_into_guest_id=VALUES(merged_into_guest_id),updated_at=VALUES(updated_at),attached_at=VALUES(attached_at),recovery_hint=VALUES(recovery_hint),payload_json=VALUES(payload_json)");
    $st->execute([$g['guestId'],$g['status'],$g['primaryClientId'],$g['attachedUserId']??null,$g['mergedIntoGuestId']??null,loom_db_dt($g['createdAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($g['updatedAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($g['attachedAt']??null),$g['recoveryHint']??null,json_encode($g,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    foreach(($g['clients']??[]) as $cid=>$meta){$c=$pdo->prepare("INSERT INTO loom_guest_clients(client_id,guest_id,first_seen,last_seen) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE guest_id=VALUES(guest_id),last_seen=VALUES(last_seen)");$c->execute([$cid,$g['guestId'],loom_db_dt($meta['firstSeen']??$g['createdAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($meta['lastSeen']??$g['updatedAt'])?:gmdate('Y-m-d H:i:s.000')]);}
  }catch(Throwable $e){}
}
function loom_guest_db_sync_attachment(array $a): void { loom_guest_db_tables();if(!loom_db_ready())return;try{$st=loom_db_pdo(true)->prepare("INSERT IGNORE INTO loom_identity_attachments(attachment_id,guest_id,user_id,source_client_id,actor_type,actor_id,mode,attached_at,summary_json) VALUES(?,?,?,?,?,?,?, ?,?)");$st->execute([$a['attachmentId'],$a['guestId'],$a['userId'],$a['sourceClientId']??null,$a['actorType'],$a['actorId']??null,$a['mode'],loom_db_dt($a['attachedAt'])?:gmdate('Y-m-d H:i:s.000'),json_encode($a['summary']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){} }

function loom_guest_new_id(): string { return 'guest_'.bin2hex(random_bytes(12)); }
function loom_guest_get(string $guestId): ?array { $guestId=safe_token($guestId);$g=loom_guest_store()['guests'][$guestId]??null;return is_array($g)?$g:null; }
function loom_guest_for_client(string $clientId): ?array { $clientId=safe_token($clientId);$s=loom_guest_store();$gid=(string)($s['clientMap'][$clientId]??'');return $gid!==''&&isset($s['guests'][$gid])?$s['guests'][$gid]:null; }
function loom_guest_root(array $g): array { $seen=[];while(($g['status']??'')==='merged'&&!empty($g['mergedIntoGuestId'])){$id=(string)$g['mergedIntoGuestId'];if(isset($seen[$id]))break;$seen[$id]=true;$next=loom_guest_get($id);if(!$next)break;$g=$next;}return $g; }
function loom_guest_canonical_client_id(string $clientId): string { $clientId=safe_token($clientId);$g=loom_guest_for_client($clientId);if(!$g)return $clientId;$g=loom_guest_root($g);return safe_token((string)($g['primaryClientId']??$clientId))?:$clientId; }
function loom_guest_public(array $g): array { $root=loom_guest_root($g);return ['guestId'=>$g['guestId'],'status'=>$g['status'],'primaryClientId'=>$g['primaryClientId'],'attachedUserId'=>$g['attachedUserId']??null,'mergedIntoGuestId'=>$g['mergedIntoGuestId']??null,'canonicalGuestId'=>$root['guestId']??$g['guestId'],'createdAt'=>$g['createdAt'],'updatedAt'=>$g['updatedAt'],'attachedAt'=>$g['attachedAt']??null,'clientCount'=>count($g['clients']??[]),'recoveryProtected'=>!empty($g['recoveryHash']),'recoveryHint'=>$g['recoveryHint']??null,'snapshotCount'=>count($g['snapshots']??[]),'attachmentCount'=>count($g['attachmentIds']??[])]; }
function loom_guest_create_for_client(string $clientId,?string $attachedUserId=null,?string $supersedesGuestId=null): array {
  $clientId=safe_token($clientId);if($clientId==='')throw new RuntimeException('Invalid client identity.');$now=server_timestamp();$gid=loom_guest_new_id();
  $g=['guestId'=>$gid,'status'=>$attachedUserId?'attached':'unattached','primaryClientId'=>$clientId,'attachedUserId'=>$attachedUserId,'attachedAt'=>$attachedUserId?$now:null,'mergedIntoGuestId'=>null,'supersedesGuestId'=>$supersedesGuestId,'createdAt'=>$now,'updatedAt'=>$now,'clients'=>[$clientId=>['firstSeen'=>$now,'lastSeen'=>$now]],'snapshotCount'=>0,'snapshots'=>[],'attachmentIds'=>[],'recoveryHash'=>null,'recoveryHint'=>null];
  $s=loom_guest_store();$s['guests'][$gid]=$g;$s['clientMap'][$clientId]=$gid;loom_guest_write_store($s);loom_guest_db_sync($g);loom_identity_audit('guest.created',['guestId'=>$gid,'clientId'=>$clientId,'attachedUserId'=>$attachedUserId,'supersedesGuestId'=>$supersedesGuestId]);return $g;
}
function loom_guest_ensure_for_client(string $clientId): array {
  $clientId=safe_token($clientId);if($clientId==='')throw new RuntimeException('Invalid client identity.');$s=loom_guest_store();$gid=(string)($s['clientMap'][$clientId]??'');$now=server_timestamp();
  if($gid!==''&&isset($s['guests'][$gid])){$g=$s['guests'][$gid];$g['clients'][$clientId]=$g['clients'][$clientId]??['firstSeen'=>$now,'lastSeen'=>$now];$g['clients'][$clientId]['lastSeen']=$now;$g['updatedAt']=$now;$s['guests'][$gid]=$g;loom_guest_write_store($s);loom_guest_db_sync($g);return loom_guest_root($g);}
  $linked=loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');return loom_guest_create_for_client($clientId,$uid?:null,null);
}
function loom_guest_map_client(string $guestId,string $clientId): array {
  $guestId=safe_token($guestId);$clientId=safe_token($clientId);$s=loom_guest_store();if(!isset($s['guests'][$guestId]))throw new RuntimeException('Guest identity not found.');$now=server_timestamp();$g=$s['guests'][$guestId];$g['clients'][$clientId]=$g['clients'][$clientId]??['firstSeen'=>$now,'lastSeen'=>$now];$g['clients'][$clientId]['lastSeen']=$now;$g['updatedAt']=$now;$s['guests'][$guestId]=$g;$s['clientMap'][$clientId]=$guestId;loom_guest_write_store($s);loom_guest_db_sync($g);return $g;
}
function loom_guest_list_for_user(string $userId): array { $out=[];foreach((loom_guest_store()['guests']??[]) as $g){$g=loom_guest_root($g);if(($g['attachedUserId']??'')===$userId)$out[$g['guestId']]=loom_guest_public($g);}return array_values($out); }
function loom_guest_all(): array { $out=[];foreach((loom_guest_store()['guests']??[]) as $g)$out[]=loom_guest_public($g);usort($out,fn($a,$b)=>strcmp((string)$b['updatedAt'],(string)$a['updatedAt']));return $out; }

function loom_guest_discover_existing_client_ids(): array {
  $ids=[];$add=function($v)use(&$ids){$v=safe_token((string)$v);if($v!==''&&str_starts_with($v,'client_'))$ids[$v]=true;};
  foreach(glob(loom_data_dir().'/users/*.json')?:[] as $file){$p=read_json_file($file);if(is_array($p))$add($p['clientId']??'');}
  if(function_exists('loom_global_profile_list_all'))foreach(loom_global_profile_list_all() as $p)if(($p['ownerType']??'')==='client')$add($p['ownerId']??'');
  if(function_exists('loom_project_identity_store'))foreach((loom_project_identity_store()['identities']??[]) as $r){$n=loom_project_identity_row_normalize($r);if(($n['ownerType']??'')==='client')$add($n['ownerId']??'');}
  foreach(glob(loom_project_state_dir().'/*.json')?:[] as $file)foreach((read_json_file($file)?:[]) as $key=>$r){$parts=explode('|',(string)$key);if(count($parts)<3)continue;$id=array_pop($parts);$type=array_pop($parts);if($type==='client')$add($id);}
  $network=read_json_file(loom_data_dir().'/users/network.json')?:[];foreach(($network['identities']??[]) as $r)if(($r['ownerType']??'')==='client')$add($r['ownerId']??'');
  if(loom_db_ready())try{$pdo=loom_db_pdo(true);foreach([
    "SELECT client_id AS cid FROM loom_client_profiles",
    "SELECT client_id AS cid FROM loom_clients",
    "SELECT owner_id AS cid FROM loom_global_profiles WHERE owner_type='client'",
    "SELECT owner_id AS cid FROM loom_project_identities WHERE owner_type='client'",
    "SELECT owner_id AS cid FROM loom_project_module_state WHERE owner_type='client'"
  ] as $sql)try{foreach($pdo->query($sql) as $r)$add($r['cid']??'');}catch(Throwable $ignored){}}catch(Throwable $e){}
  return array_keys($ids);
}
function loom_guest_backfill_existing_clients(): int {
  $n=0;foreach(loom_guest_discover_existing_client_ids() as $cid){if(loom_guest_for_client($cid))continue;$linked=loom_account_user_for_client($cid);$uid=(string)($linked['user_id']??$linked['userId']??'');loom_guest_create_for_client($cid,$uid?:null,null);$n++;}return $n;
}

function loom_guest_copy_file(string $src,string $dst): ?string { if(!is_file($src))return null;ensure_dir(dirname($dst));if(!@copy($src,$dst))return null;@chmod($dst,0664);return $dst; }
function loom_guest_archive_assets(string $guestId,array $clients): array {
  $out=[];$assetDir=loom_guest_asset_dir($guestId);
  foreach($clients as $cid){
    if(function_exists('loom_global_avatar_find')){$f=loom_global_avatar_find('client',$cid);if($f){$dst=$assetDir.'/global-'.$cid.'.'.$f['ext'];if(loom_guest_copy_file($f['file'],$dst))$out[]=['kind'=>'global-avatar','clientId'=>$cid,'path'=>str_replace(root_dir().'/','',$dst)];}}
    $base=loom_data_dir().'/avatars/projects';if(is_dir($base))foreach(glob($base.'/*')?:[] as $projectDir){if(!is_dir($projectDir))continue;$project=basename($projectDir);$hash=function_exists('loom_avatar_hash')?loom_avatar_hash($cid):hash('sha256',$cid);foreach(['webp','png','jpg','jpeg'] as $ext){$src=$projectDir.'/client_'.$hash.'.'.$ext;if(is_file($src)){$dst=$assetDir.'/project-'.$project.'-'.$cid.'.'.$ext;if(loom_guest_copy_file($src,$dst))$out[]=['kind'=>'project-avatar','project'=>$project,'clientId'=>$cid,'path'=>str_replace(root_dir().'/','',$dst)];}}}
  }
  return $out;
}
function loom_guest_snapshot(string $guestId,string $reason='attachment'): array {
  $g=loom_guest_get($guestId);if(!$g)throw new RuntimeException('Guest identity not found.');$g=loom_guest_root($g);$clients=array_keys($g['clients']??[]);$canonical=$g['primaryClientId'];$snapId='snap_'.bin2hex(random_bytes(8));$now=server_timestamp();
  $clientProfiles=[];foreach($clients as $cid){$p=loom_db_ready()&&function_exists('loom_db_read_client_profile')?loom_db_read_client_profile($cid):null;if(!is_array($p)){$f=loom_data_dir().'/users/'.hash('sha256',$cid).'.json';$p=read_json_file($f);}if($p)$clientProfiles[$cid]=$p;}
  $projectIdentities=[];foreach($clients as $cid)if(function_exists('loom_project_identity_list_for_owner'))$projectIdentities[$cid]=loom_project_identity_list_for_owner('client',$cid);
  $states=[];foreach($clients as $cid)if(function_exists('loom_project_state_rows_for_owner'))$states[$cid]=loom_project_state_rows_for_owner('client',$cid);
  $network=[];foreach($clients as $cid)if(function_exists('loom_identity_ip_history'))$network[$cid]=loom_identity_ip_history('client',$cid);
  $snapshot=['snapshotId'=>$snapId,'guestId'=>$guestId,'reason'=>$reason,'createdAt'=>$now,'clients'=>$clients,'canonicalClientId'=>$canonical,'globalProfile'=>function_exists('loom_global_profile_get')?loom_global_profile_get('client',$canonical):null,'clientProfiles'=>$clientProfiles,'projectIdentities'=>$projectIdentities,'projectStates'=>$states,'network'=>$network,'assets'=>loom_guest_archive_assets($guestId,$clients)];
  $file=loom_guest_snapshot_dir($guestId).'/'.$snapId.'.json';@file_put_contents($file,json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  $s=loom_guest_store();$gg=$s['guests'][$guestId]??$g;$gg['snapshots'][]=['snapshotId'=>$snapId,'reason'=>$reason,'createdAt'=>$now,'file'=>str_replace(root_dir().'/','',$file)];$gg['snapshotCount']=count($gg['snapshots']);$gg['updatedAt']=$now;$s['guests'][$guestId]=$gg;loom_guest_write_store($s);loom_guest_db_sync($gg);loom_identity_audit('guest.snapshot',['guestId'=>$guestId,'snapshotId'=>$snapId,'reason'=>$reason]);return $snapshot;
}

function loom_guest_record_conflicts_for_client(string $clientId,string $userId,string $project,string $module,array $conflicts): void {
  if(!$conflicts)return;$g=loom_guest_for_client($clientId);$guestId=$g['guestId']??null;$s=loom_guest_store();
  foreach($conflicts as $c){$row=['conflictId'=>event_id('conflict'),'guestId'=>$guestId,'userId'=>$userId,'project'=>$project,'module'=>$module,'path'=>$c['path']??'','guestValue'=>$c['guest']??null,'userValue'=>$c['user']??null,'resolution'=>$c['resolution']??'preserve-both-newest-active','createdAt'=>server_timestamp(),'resolvedAt'=>null];$s['conflicts'][]=$row;if(loom_db_ready())try{loom_guest_db_tables();$st=loom_db_pdo(true)->prepare("INSERT INTO loom_identity_merge_conflicts(conflict_id,guest_id,user_id,project_slug,module_id,path_text,guest_value_json,user_value_json,resolution,created_at) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(3))");$st->execute([$row['conflictId'],$guestId,$userId,$project,$module,$row['path'],json_encode($row['guestValue'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode($row['userValue'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$row['resolution']]);}catch(Throwable $e){}}
  if(count($s['conflicts'])>5000)$s['conflicts']=array_slice($s['conflicts'],-5000);loom_guest_write_store($s);
}

function loom_guest_link_mapping_only(string $clientId,string $userId): void {
  if(function_exists('loom_bind_client_mapping_only')){loom_bind_client_mapping_only($clientId,$userId);return;}
  if(loom_db_ready()){$pdo=loom_db_pdo(true);$pdo->prepare("INSERT INTO loom_user_clients(client_id,user_id,linked_at) VALUES(?,?,UTC_TIMESTAMP(3)) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),linked_at=VALUES(linked_at)")->execute([$clientId,$userId]);$pdo->prepare("UPDATE loom_clients SET user_id=? WHERE client_id=?")->execute([$userId,$clientId]);}
}
function loom_guest_record_attachment(array $g,string $userId,string $sourceClientId,string $actorType,string $actorId,string $mode,array $summary): array {
  $a=['attachmentId'=>event_id('attach'),'guestId'=>$g['guestId'],'userId'=>$userId,'sourceClientId'=>$sourceClientId,'actorType'=>$actorType,'actorId'=>$actorId?:null,'mode'=>$mode,'attachedAt'=>server_timestamp(),'summary'=>$summary];$s=loom_guest_store();$s['attachments'][]=$a;$gg=$s['guests'][$g['guestId']]??$g;$gg['attachmentIds'][]=$a['attachmentId'];$gg['status']='attached';$gg['attachedUserId']=$userId;$gg['attachedAt']=$a['attachedAt'];$gg['updatedAt']=$a['attachedAt'];$s['guests'][$g['guestId']]=$gg;loom_guest_write_store($s);loom_guest_db_sync($gg);loom_guest_db_sync_attachment($a);loom_identity_audit('guest.attached',$a);return $a;
}
function loom_guest_attach_client_to_user(string $clientId,string $userId,string $actorType='self',string $actorId='',string $mode='sign-in'): array {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')throw new RuntimeException('Invalid guest attachment.');$g=loom_guest_ensure_for_client($clientId);$g=loom_guest_root($g);
  if(!empty($g['attachedUserId'])&&$g['attachedUserId']!==$userId){
    // Do not leak one account's guest history into another account simply because
    // the same browser later signs in as somebody else.
    $old=$g;$g=loom_guest_create_for_client($clientId,null,$old['guestId']);loom_guest_link_mapping_only($clientId,$userId);$a=loom_guest_record_attachment($g,$userId,$clientId,$actorType,$actorId,$mode.'-fresh-device',['sourcePreservedGuestId'=>$old['guestId'],'mergedModules'=>0,'conflicts'=>0,'note'=>'Prior guest history belongs to another permanent user and was not imported.']);return $a;
  }
  if(($g['attachedUserId']??'')===$userId){foreach(array_keys($g['clients']??[]) as $cid)loom_guest_link_mapping_only($cid,$userId);return ['attachmentId'=>null,'guestId'=>$g['guestId'],'userId'=>$userId,'mode'=>'already-attached','attachedAt'=>$g['attachedAt']??null,'summary'=>['alreadyAttached'=>true]];}

  $snapshot=loom_guest_snapshot($g['guestId'],'before-'.$mode);$clients=array_keys($g['clients']??[]);$summary=['snapshotId'=>$snapshot['snapshotId'],'clients'=>count($clients),'mergedModules'=>0,'conflicts'=>0,'globalProfile'=>'preserved','projectIdentities'=>0,'avatarsArchived'=>count($snapshot['assets']??[])];

  // Merge project data first while client ownership is still intact. Source rows remain.
  foreach($clients as $cid)if(function_exists('loom_project_state_attach_client')){$m=loom_project_state_attach_client($cid,$userId);$summary['mergedModules']+=($m['modules']??0);$summary['conflicts']+=($m['conflicts']??0);}

  // Profile/identity structures must move because their live tables enforce unique
  // usernames. Their original values and assets are retained in the immutable guest snapshot.
  $canonical=(string)$g['primaryClientId'];
  if(function_exists('loom_global_profile_promote_client'))try{loom_global_profile_promote_client($canonical,$userId);$summary['globalProfile']='attached';}catch(Throwable $e){$summary['globalProfile']='conflict-preserved-in-snapshot';}
  foreach($clients as $cid){
    if(function_exists('loom_project_identity_list_for_owner'))$summary['projectIdentities']+=count(loom_project_identity_list_for_owner('client',$cid));
    if(function_exists('loom_project_identity_promote_client'))try{loom_project_identity_promote_client($cid,$userId);}catch(Throwable $e){}
    if(function_exists('loom_avatar_promote_client_to_user'))try{loom_avatar_promote_client_to_user($cid,$userId);}catch(Throwable $e){}
    if(function_exists('loom_promote_client_moderation_to_user'))try{loom_promote_client_moderation_to_user($cid,$userId);}catch(Throwable $e){}
    if(function_exists('loom_access_promote_client_to_user'))try{$a=loom_access_promote_client_to_user($cid,$userId);$summary['accessGrantsPromoted']=($summary['accessGrantsPromoted']??0)+(int)($a['moved']??0);}catch(Throwable $e){}
  }
  foreach($clients as $cid)loom_guest_link_mapping_only($cid,$userId);
  return loom_guest_record_attachment($g,$userId,$clientId,$actorType,$actorId,$mode,$summary);
}

function loom_guest_issue_recovery_code(string $guestId,string $actorType='self'): array {
  $g=loom_guest_get($guestId);if(!$g)throw new RuntimeException('Guest identity not found.');$g=loom_guest_root($g);if(($g['status']??'')==='attached')throw new RuntimeException('This Guest Identity is already attached to a permanent account. Use account sign-in for recovery.');
  $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$raw='';for($i=0;$i<16;$i++)$raw.=$alphabet[random_int(0,strlen($alphabet)-1)];$code='LOOM-'.substr($raw,0,4).'-'.substr($raw,4,4).'-'.substr($raw,8,4).'-'.substr($raw,12,4);$hash=hash('sha256',$raw);$hint='••••-'.substr($raw,-4);$now=server_timestamp();
  $s=loom_guest_store();$gg=$s['guests'][$g['guestId']];if(!empty($gg['recoveryHash']))unset($s['recoveryIndex'][$gg['recoveryHash']]);$gg['recoveryHash']=$hash;$gg['recoveryHint']=$hint;$gg['updatedAt']=$now;$s['guests'][$g['guestId']]=$gg;$s['recoveryIndex'][$hash]=$g['guestId'];loom_guest_write_store($s);loom_guest_db_sync($gg);
  if(loom_db_ready())try{loom_guest_db_tables();$pdo=loom_db_pdo(true);$pdo->prepare("UPDATE loom_guest_recovery SET revoked_at=UTC_TIMESTAMP(3) WHERE guest_id=? AND revoked_at IS NULL")->execute([$g['guestId']]);$pdo->prepare("INSERT INTO loom_guest_recovery(recovery_hash,guest_id,created_at) VALUES(?,?,UTC_TIMESTAMP(3))")->execute([$hash,$g['guestId']]);}catch(Throwable $e){}
  loom_identity_audit('guest.recovery-issued',['guestId'=>$g['guestId'],'actorType'=>$actorType,'hint'=>$hint]);return ['guest'=>loom_guest_public($gg),'recoveryCode'=>$code,'warning'=>'This code is shown only now. Store it somewhere safe.'];
}
function loom_guest_normalize_recovery_code(string $code): string { return strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)??''); }
function loom_guest_recovery_lookup(string $code): ?array { $norm=loom_guest_normalize_recovery_code($code);if(str_starts_with($norm,'LOOM'))$norm=substr($norm,4);if(strlen($norm)!==16)return null;$hash=hash('sha256',$norm);$s=loom_guest_store();$gid=(string)($s['recoveryIndex'][$hash]??'');if($gid===''||!isset($s['guests'][$gid]))return null;return ['hash'=>$hash,'guest'=>$s['guests'][$gid]]; }
function loom_guest_merge_into_guest(string $sourceGuestId,string $targetGuestId,string $currentClientId): array {
  $source=loom_guest_get($sourceGuestId);$target=loom_guest_get($targetGuestId);if(!$source||!$target)throw new RuntimeException('Guest identity not found.');$source=loom_guest_root($source);$target=loom_guest_root($target);if($source['guestId']===$target['guestId'])return $target;loom_guest_snapshot($source['guestId'],'before-guest-recovery-merge');$dstClient=(string)$target['primaryClientId'];$summary=['modules'=>0,'conflicts'=>0,'sourceClients'=>count($source['clients']??[])];
  foreach(array_keys($source['clients']??[]) as $srcClient){if(function_exists('loom_project_state_merge_client_to_client')){$m=loom_project_state_merge_client_to_client($srcClient,$dstClient);$summary['modules']+=($m['modules']??0);$summary['conflicts']+=($m['conflicts']??0);}}
  $s=loom_guest_store();$source=$s['guests'][$source['guestId']];$target=$s['guests'][$target['guestId']];$now=server_timestamp();foreach(($source['clients']??[]) as $cid=>$meta){$target['clients'][$cid]=$meta;$target['clients'][$cid]['lastSeen']=$target['clients'][$cid]['lastSeen']??$now;$s['clientMap'][$cid]=$target['guestId'];}$target['clients'][$currentClientId]=$target['clients'][$currentClientId]??['firstSeen'=>$now,'lastSeen'=>$now];$target['clients'][$currentClientId]['lastSeen']=$now;$s['clientMap'][$currentClientId]=$target['guestId'];$source['status']='merged';$source['mergedIntoGuestId']=$target['guestId'];$source['updatedAt']=$now;$target['updatedAt']=$now;$s['guests'][$source['guestId']]=$source;$s['guests'][$target['guestId']]=$target;loom_guest_write_store($s);loom_guest_db_sync($source);loom_guest_db_sync($target);loom_identity_audit('guest.merged-into-guest',['sourceGuestId'=>$source['guestId'],'targetGuestId'=>$target['guestId'],'currentClientId'=>$currentClientId,'summary'=>$summary]);return $target;
}
function loom_guest_recover_to_client(string $clientId,string $code): array {
  $lookup=loom_guest_recovery_lookup($code);if(!$lookup)throw new RuntimeException('Recovery code not recognized.');$target=loom_guest_root($lookup['guest']);if(($target['status']??'')==='attached')throw new RuntimeException('This Guest Identity has already been attached to a permanent account. Sign in to that account or contact support.');
  $current=loom_guest_ensure_for_client($clientId);$current=loom_guest_root($current);if($current['guestId']!==$target['guestId'])$target=loom_guest_merge_into_guest($current['guestId'],$target['guestId'],$clientId);else $target=loom_guest_map_client($target['guestId'],$clientId);
  if(loom_db_ready())try{loom_guest_db_tables();loom_db_pdo(true)->prepare("UPDATE loom_guest_recovery SET last_used_at=UTC_TIMESTAMP(3) WHERE recovery_hash=?")->execute([$lookup['hash']]);}catch(Throwable $e){}
  loom_identity_audit('guest.recovered',['guestId'=>$target['guestId'],'clientId'=>$clientId]);return loom_guest_public($target);
}
function loom_guest_admin_merge(string $sourceGuestId,string $targetGuestId,string $actorId=''): array {
  $source=loom_guest_get($sourceGuestId);$target=loom_guest_get($targetGuestId);if(!$source||!$target)throw new RuntimeException('Guest identity not found.');$source=loom_guest_root($source);$target=loom_guest_root($target);if($source['guestId']===$target['guestId'])throw new RuntimeException('Choose two different Guest Identities.');if(($source['status']??'')==='attached'||($target['status']??'')==='attached')throw new RuntimeException('Admin guest-to-guest merge is only for unattached Guest Identities. Attach to a permanent user instead.');
  $snapshot=loom_guest_snapshot($source['guestId'],'before-admin-guest-merge');$summary=['snapshotId'=>$snapshot['snapshotId'],'modules'=>0,'conflicts'=>0,'sourceClients'=>count($source['clients']??[])];$targetClient=(string)$target['primaryClientId'];
  foreach(array_keys($source['clients']??[]) as $cid){if(function_exists('loom_project_state_merge_client_to_client')){$m=loom_project_state_merge_client_to_client($cid,$targetClient);$summary['modules']+=($m['modules']??0);$summary['conflicts']+=($m['conflicts']??0);}}
  $store=loom_guest_store();$src=$store['guests'][$source['guestId']];$dst=$store['guests'][$target['guestId']];foreach(($src['clients']??[]) as $cid=>$meta){$dst['clients'][$cid]=$meta;$store['clientMap'][$cid]=$dst['guestId'];}$src['status']='merged';$src['mergedIntoGuestId']=$dst['guestId'];$src['updatedAt']=server_timestamp();$dst['updatedAt']=server_timestamp();$store['guests'][$src['guestId']]=$src;$store['guests'][$dst['guestId']]=$dst;loom_guest_write_store($store);loom_guest_db_sync($src);loom_guest_db_sync($dst);loom_identity_audit('guest.admin-merged',['sourceGuestId'=>$src['guestId'],'targetGuestId'=>$dst['guestId'],'actorId'=>$actorId,'summary'=>$summary]);return ['source'=>loom_guest_public($src),'target'=>loom_guest_public($dst),'summary'=>$summary];
}

function loom_guest_ip_set(array $g): array { $out=[];if(function_exists('loom_identity_ip_history'))foreach(array_keys($g['clients']??[]) as $cid)foreach(loom_identity_ip_history('client',$cid) as $r){$ip=(string)($r['ip']??'');if($ip!=='')$out[$ip]=true;}return array_keys($out); }
function loom_guest_username_set(array $g): array { $out=[];foreach(array_keys($g['clients']??[]) as $cid){if(function_exists('loom_global_profile_get')){$p=loom_global_profile_get('client',$cid);$u=loom_username_norm((string)($p['username']??''));if($u!=='')$out[$u]=true;}if(function_exists('loom_project_identity_list_for_owner'))foreach(loom_project_identity_list_for_owner('client',$cid) as $p){$u=loom_username_norm((string)($p['effectiveUsername']??$p['username']??''));if($u!=='')$out[$u]=true;}}return array_keys($out); }
function loom_guest_project_set(array $g): array { $out=[];foreach(array_keys($g['clients']??[]) as $cid){if(function_exists('loom_project_identity_list_for_owner'))foreach(loom_project_identity_list_for_owner('client',$cid) as $p)$out[(string)$p['project']]=true;if(function_exists('loom_project_state_rows_for_owner'))foreach(loom_project_state_rows_for_owner('client',$cid) as $r)$out[(string)$r['project']]=true;}return array_keys($out); }
function loom_guest_related_candidates(string $guestId,int $limit=20): array {
  $base=loom_guest_get($guestId);if(!$base)return [];$base=loom_guest_root($base);$ips=loom_guest_ip_set($base);$names=loom_guest_username_set($base);$projects=loom_guest_project_set($base);$baseActivity=loom_guest_activity_summary($base);$baseActions=array_column($baseActivity['topActions']??[],'actionId');$baseLast=loom_state_ts($baseActivity['lastActivity']??$base['updatedAt']??null);$rows=[];
  foreach((loom_guest_store()['guests']??[]) as $id=>$raw){if($id===$base['guestId'])continue;$g=loom_guest_root($raw);if($g['guestId']!==$id)continue;$signals=[];$score=0;
    $sharedIps=array_values(array_intersect($ips,loom_guest_ip_set($g)));if($sharedIps){$score+=min(2,count($sharedIps));$signals[]=['kind'=>'shared-ip','weight'=>'low','detail'=>count($sharedIps).' shared network address(es)'];}
    $sharedNames=array_values(array_intersect($names,loom_guest_username_set($g)));if($sharedNames){$score+=5;$signals[]=['kind'=>'matching-username','weight'=>'medium','detail'=>count($sharedNames).' matching username value(s)'];}
    $sharedProjects=array_values(array_intersect($projects,loom_guest_project_set($g)));if($sharedProjects){$score+=min(3,count($sharedProjects));$signals[]=['kind'=>'project-overlap','weight'=>'low','detail'=>count($sharedProjects).' shared project(s)'];}
    $otherActivity=loom_guest_activity_summary($g);$sharedActions=array_values(array_intersect($baseActions,array_column($otherActivity['topActions']??[],'actionId')));if($sharedActions){$score+=min(4,count($sharedActions));$signals[]=['kind'=>'action-pattern-overlap','weight'=>'low','detail'=>count($sharedActions).' shared high-frequency action type(s)'];}
    $otherLast=loom_state_ts($otherActivity['lastActivity']??$g['updatedAt']??null);if($baseLast&&$otherLast){$days=abs($baseLast-$otherLast)/86400;if($days<=1){$score+=2;$signals[]=['kind'=>'activity-time-proximity','weight'=>'low','detail'=>'Last activity within about one day'];}elseif($days<=7){$score+=1;$signals[]=['kind'=>'activity-time-proximity','weight'=>'low','detail'=>'Last activity within about one week'];}}
    if(($base['attachedUserId']??'')!==''&&($base['attachedUserId']??'')===($g['attachedUserId']??'')){$score+=100;$signals[]=['kind'=>'same-attached-user','weight'=>'strong','detail'=>'Already attached to the same permanent user'];}
    if($score>0)$rows[]=['guest'=>loom_guest_public($g),'evidenceScore'=>$score,'signals'=>$signals];
  }
  usort($rows,fn($a,$b)=>$b['evidenceScore']<=>$a['evidenceScore']);return array_slice($rows,0,$limit);
}
function loom_guest_activity_summary(array $g): array {
  $clients=array_fill_keys(array_keys($g['clients']??[]),true);$projects=[];$events=0;$actions=[];$first=null;$last=null;
  foreach(glob(loom_data_dir().'/logs/*/*.jsonl')?:[] as $file){$project=basename(dirname($file));foreach(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$e=json_decode($line,true);if(!is_array($e)||!isset($clients[(string)($e['clientId']??'')]))continue;$events++;$projects[$project]=($projects[$project]??0)+1;$aid=(string)($e['actionId']??'');if($aid!=='')$actions[$aid]=($actions[$aid]??0)+1;$ts=(string)($e['serverTimestamp']??$e['clientTimestamp']??'');if($ts!==''){$first=$first===null?$ts:min($first,$ts);$last=$last===null?$ts:max($last,$ts);}}}
  arsort($actions);$top=[];foreach(array_slice($actions,0,12,true) as $id=>$count)$top[]=['actionId'=>$id,'count'=>$count];return ['eventCount'=>$events,'projects'=>$projects,'firstActivity'=>$first,'lastActivity'=>$last,'topActions'=>$top];
}
function loom_guest_detail(string $guestId): array { $g=loom_guest_get($guestId);if(!$g)throw new RuntimeException('Guest identity not found.');$root=loom_guest_root($g);$global=loom_global_profile_get('client',$g['primaryClientId']);$attachments=[];$s=loom_guest_store();foreach(($s['attachments']??[]) as $a)if(($a['guestId']??'')===$g['guestId'])$attachments[]=$a;$conflicts=[];foreach(($s['conflicts']??[]) as $c)if(($c['guestId']??'')===$g['guestId'])$conflicts[]=$c;return ['guest'=>loom_guest_public($g),'canonicalGuest'=>$root['guestId']!==$g['guestId']?loom_guest_public($root):null,'globalProfile'=>$global,'clients'=>$g['clients']??[],'ipAddresses'=>loom_guest_ip_set($g),'projects'=>loom_guest_project_set($g),'activity'=>loom_guest_activity_summary($g),'attachments'=>$attachments,'conflicts'=>array_slice(array_reverse($conflicts),0,100),'relatedGuests'=>($g['status']??'')==='unattached'?loom_guest_related_candidates($g['guestId']):[]]; }
function loom_guest_migrate_to_db(): int { if(!loom_db_ready())return 0;loom_guest_backfill_existing_clients();$n=0;$s=loom_guest_store();foreach(($s['guests']??[]) as $g){loom_guest_db_sync($g);$n++;}foreach(($s['attachments']??[]) as $a)loom_guest_db_sync_attachment($a);return $n; }
