<?php
// @loom-file release=0.15.29 revision=3 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);$clientId=safe_token((string)($body['clientId']??''));loom_require_admin($clientId);$action=(string)($body['action']??'list');
function identity_confirm(array $b): void { if((string)($b['confirmPhrase']??'')!=='CONFIRM')throw new RuntimeException('Type CONFIRM to perform this identity operation.'); }
function identity_active_guest(string $guestId): array { $g=loom_guest_get($guestId);if(!$g)throw new RuntimeException('Guest identity not found.');if(($g['status']??'')==='merged')throw new RuntimeException('That Guest Identity has already been merged into '.($g['mergedIntoGuestId']??'its canonical target').'. Open the canonical Guest Identity instead.');return $g; }
function identity_user_rows(): array { $out=[];if(loom_db_ready())try{foreach(loom_db_pdo(true)->query("SELECT user_id,email,privilege,created_at,updated_at FROM loom_users ORDER BY updated_at DESC") as $u)$out[]=['userId'=>$u['user_id'],'email'=>$u['email'],'privilege'=>$u['privilege'],'createdAt'=>$u['created_at'],'updatedAt'=>$u['updated_at'],'globalProfile'=>loom_global_profile_get('user',$u['user_id'])];}catch(Throwable $e){}else foreach((loom_temp_account_store()['users']??[]) as $u){$uid=(string)($u['userId']??'');$out[]=['userId'=>$uid,'email'=>$u['email']??null,'privilege'=>$u['privilege']??'User','createdAt'=>$u['createdAt']??null,'updatedAt'=>$u['updatedAt']??null,'globalProfile'=>loom_global_profile_get('user',$uid)];}return $out; }
function identity_guest_rows(): array {
  $out=[];
  foreach(loom_guest_all() as $g){
    $uid=safe_token((string)($g['attachedUserId']??''));
    $profile=$uid!==''?loom_global_profile_get('user',$uid):loom_global_profile_get('client',(string)($g['primaryClientId']??''));
    $username=loom_clean_username((string)($profile['username']??''));
    $email=null;
    if($uid!==''){$u=loom_account_user_by_id($uid);$email=is_array($u)?($u['email']??null):null;}
    $g['username']=$username!==''?$username:null;$g['displayName']=$g['username']?:('Guest '.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',(string)($g['guestId']??'')),-6)));$g['email']=$email;
    $out[]=$g;
  }
  usort($out,fn($a,$b)=>strcasecmp((string)($a['displayName']??''),(string)($b['displayName']??'')));
  return $out;
}
try{
  if($action==='list'){json_out(['ok'=>true,'guests'=>identity_guest_rows(),'users'=>identity_user_rows(),'backfilledGuests'=>0,'legacyDiscoveryDeferred'=>true]); }
  if($action==='detail'){ $detail=loom_guest_detail((string)($body['guestId']??''));$g=$detail['guest']??[];$uid=safe_token((string)($g['attachedUserId']??''));$profile=$uid!==''?loom_global_profile_get('user',$uid):($detail['globalProfile']??[]);$detail['displayUsername']=loom_clean_username((string)($profile['username']??''));json_out(['ok'=>true,'detail'=>$detail,'users'=>identity_user_rows()]); }
  if($action==='issue-recovery'){identity_confirm($body);$gid=safe_token((string)($body['guestId']??''));identity_active_guest($gid);$r=loom_guest_issue_recovery_code($gid,'admin');loom_admin_audit('guest.recovery.issue','guest',$gid,'',['identityOperation'=>true]);json_out(['ok'=>true]+$r);}
  if($action==='attach'){identity_confirm($body);$gid=safe_token((string)($body['guestId']??''));$uid=safe_token((string)($body['userId']??''));$g=identity_active_guest($gid);if(!loom_account_user_by_id($uid))throw new RuntimeException('Permanent user not found.');$admin=loom_auth_user();$adminId=(string)($admin['user_id']??$admin['userId']??'');$a=loom_guest_attach_client_to_user((string)$g['primaryClientId'],$uid,'admin',$adminId,'admin-attach');loom_admin_audit('guest.attach','guest',$gid,'',['userId'=>$uid,'attachmentId'=>$a['attachmentId']??null]);json_out(['ok'=>true,'attachment'=>$a,'detail'=>loom_guest_detail($gid)]);}
  if($action==='convert'){identity_confirm($body);$gid=safe_token((string)($body['guestId']??''));$email=trim((string)($body['email']??''));$password=(string)($body['password']??'');$g=identity_active_guest($gid);if(($g['status']??'')==='attached')throw new RuntimeException('Guest identity is already attached.');$u=loom_create_account_record($email,$password,'User');$uid=(string)($u['user_id']??$u['userId']??'');$admin=loom_auth_user();$adminId=(string)($admin['user_id']??$admin['userId']??'');$a=loom_guest_attach_client_to_user((string)$g['primaryClientId'],$uid,'admin',$adminId,'admin-convert');loom_admin_audit('guest.convert-to-account','guest',$gid,'',['userId'=>$uid,'email'=>$email]);json_out(['ok'=>true,'userId'=>$uid,'attachment'=>$a,'detail'=>loom_guest_detail($gid)]);}
  if($action==='merge-guests'){identity_confirm($body);$source=safe_token((string)($body['sourceGuestId']??''));$target=safe_token((string)($body['targetGuestId']??''));identity_active_guest($source);identity_active_guest($target);$admin=loom_auth_user();$adminId=(string)($admin['user_id']??$admin['userId']??'');$m=loom_guest_admin_merge($source,$target,$adminId);loom_admin_audit('guest.merge','guest',$source,'',['targetGuestId'=>$target,'summary'=>$m['summary']]);json_out(['ok'=>true,'merge'=>$m,'detail'=>loom_guest_detail($target)]);}
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}json_out(['ok'=>false,'error'=>'Unknown action'],400);
