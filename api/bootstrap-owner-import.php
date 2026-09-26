<?php
// @loom-file release=0.15.68 revision=1 policy=package-priority
// First-run System Owner restoration from a verified portable User or Guest bundle.
declare(strict_types=1);
require_once __DIR__.'/_common.php';
require_once __DIR__.'/_backup_restore.php';

function loom_bootstrap_owner_profile_bind_client(string $clientId,array $payload): void {
  $profileIds=(array)($payload['graph']['guestProfileIds']??[]);
  if(!$profileIds)return;
  $pid=safe_token((string)$profileIds[0]);if($pid==='')return;
  $s=loom_guest_profiles_store();if(!is_array($s['profiles'][$pid]??null))return;
  $s['clientProfileMap'][$clientId]=$pid;
  loom_guest_profiles_write($s);
}

function loom_bootstrap_owner_claim(string $clientId,?string $userId,string $portableType,string $sourceId): array {
  if(loom_admin_identity()!==null)throw new RuntimeException('Initial System Owner setup is already complete. Use Backup & Restore for normal imports.');
  $clientId=safe_token($clientId);if($clientId===''||!str_starts_with($clientId,'client_'))throw new RuntimeException('A valid LOOM browser identity is required.');
  $userId=safe_token((string)$userId);
  $token='adm_'.bin2hex(random_bytes(32));
  $state=[
    'schemaVersion'=>'1.0',
    'clientId'=>$clientId,
    'userId'=>$userId!==''?$userId:null,
    'tokenHash'=>password_hash($token,PASSWORD_DEFAULT),
    'createdAt'=>server_timestamp(),
    'createdEpochMs'=>server_epoch_ms(),
    'bootstrapMethod'=>'portable-system-owner-restore',
    'portableSourceType'=>$portableType,
    'portableSourceId'=>$sourceId,
  ];
  loom_write_admin_identity($state);
  loom_set_admin_cookie($token);$_COOKIE[loom_admin_cookie_name()]=$token;
  if($userId!==''&&function_exists('loom_link_admin_to_user_if_applicable'))loom_link_admin_to_user_if_applicable($clientId,$userId);
  try{loom_issue_admin_navigation_cookie($clientId,900);}catch(Throwable $e){}
  return $state;
}

try{
  if(($_SERVER['REQUEST_METHOD']??'')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
  if(loom_admin_identity()!==null)json_out(['ok'=>false,'error'=>'Initial System Owner setup is already complete. Use Backup & Restore for normal imports.'],409);
  $clientId=safe_token((string)($_POST['clientId']??''));
  if($clientId===''||!str_starts_with($clientId,'client_'))throw new RuntimeException('A valid LOOM browser identity is required.');
  if(empty($_FILES['bundle']))throw new RuntimeException('Choose your previous System Owner portable User or Guest ZIP.');

  // Ensure this fresh browser has a normal Guest shell before restoration; it is
  // merged/attached into the imported owner identity after the payload is verified.
  $currentGuest=loom_guest_ensure_for_client($clientId);$currentGuest=loom_guest_root($currentGuest);
  $currentGuestId=safe_token((string)($currentGuest['guestId']??''));

  $staged=loom_backup_store_upload($_FILES['bundle']);
  $z=new ZipArchive();
  if($z->open($staged['path'])!==true)throw new RuntimeException('Could not open the verified owner bundle.');
  $manifest=(array)$staged['manifest'];$type=(string)($manifest['exportType']??'');
  $result=null;$display='';$sourceId='';$userId='';$payload=[];
  try{
    if($type==='user'){
      $path=(string)($manifest['user']['payloadPath']??'payload/user/user-portable.json');$raw=$z->getFromName($path);$payload=is_string($raw)?json_decode($raw,true):null;
      if(!is_array($payload)||($payload['format']??'')!==LOOM_USER_PORTABLE_FORMAT)throw new RuntimeException('This is not a valid portable LOOM user bundle.');
      if(empty($payload['wasSystemOwner']))throw new RuntimeException('That user bundle was not exported from the source installation\'s System Owner. Initial Admin restore only accepts the prior System Owner bundle.');
      $preview=loom_user_portability_preview_payload($payload);if(empty($preview['canApply']))throw new RuntimeException('Owner user import conflict: '.implode(' ',(array)$preview['conflicts']));
      $assets=is_array($payload['assets']??null)?$payload['assets']:[];$result=loom_user_portability_import($z,$payload,$assets,$preview['mode']==='exact-create'?'create-new':'merge');
      $userId=safe_token((string)($result['targetUserId']??''));$sourceId=safe_token((string)($payload['account']['userId']??''));$display=(string)($payload['displayName']??$sourceId);
      // Attach this brand-new browser into the restored permanent person's provenance.
      if($userId!=='')loom_bind_client_to_user($clientId,$userId);
    }elseif($type==='guest'){
      $path=(string)($manifest['guest']['payloadPath']??'payload/guest/guest-portable.json');$raw=$z->getFromName($path);$payload=is_string($raw)?json_decode($raw,true):null;
      if(!is_array($payload)||($payload['format']??'')!==LOOM_GUEST_PORTABLE_FORMAT)throw new RuntimeException('This is not a valid portable LOOM Guest bundle.');
      if(empty($payload['wasSystemOwner']))throw new RuntimeException('That Guest bundle was not exported from the source installation\'s System Owner. Initial Admin restore only accepts the prior System Owner bundle.');
      $preview=loom_guest_portability_preview_payload($payload,$clientId);if(empty($preview['canApply']))throw new RuntimeException('Owner Guest import conflict: '.implode(' ',(array)$preview['conflicts']));
      $assets=is_array($payload['assets']??null)?$payload['assets']:[];$result=loom_guest_portability_import($z,$payload,$assets,$preview['mode']==='exact-create'?'create-new':'merge',$clientId);
      $sourceId=safe_token((string)($result['targetGuestId']??$payload['guest']['guestId']??''));$display=(string)($payload['displayName']??$sourceId);
      // A fresh browser necessarily has a fresh Guest shell. Merge that empty shell
      // into the imported historical owner instead of replacing the imported IDs.
      $target=loom_guest_get($sourceId);if(!is_array($target))throw new RuntimeException('The restored Guest owner could not be resolved.');$target=loom_guest_root($target);$targetId=safe_token((string)($target['guestId']??''));
      if($currentGuestId!==''&&$currentGuestId!==$targetId){loom_guest_admin_merge($currentGuestId,$targetId,'portable-system-owner-restore');}
      else{loom_guest_map_client($targetId,$clientId);}
      loom_bootstrap_owner_profile_bind_client($clientId,$payload);
      $sourceId=$targetId;
    }else{
      throw new RuntimeException('Initial Admin restore accepts a portable User or Guest bundle only.');
    }
  }finally{$z->close();}

  $owner=loom_bootstrap_owner_claim($clientId,$userId,$type,$sourceId);
  loom_audit_record('admin.bootstrap.portable-owner-restored',['clientId'=>$clientId,'details'=>['portableType'=>$type,'sourceId'=>$sourceId,'userId'=>$userId?:null,'displayName'=>$display]],'System Owner restored from portable person bundle.');
  loom_backup_remove_tree(loom_backup_root().'/'.$staged['importId']);
  json_out(['ok'=>true,'restored'=>['type'=>$type,'sourceId'=>$sourceId,'userId'=>$userId?:null,'displayName'=>$display,'systemOwner'=>true],'result'=>$result,'bootstrapMethod'=>$owner['bootstrapMethod']]);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],500);}
