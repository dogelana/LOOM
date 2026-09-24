<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$clientId=safe_token((string)($body['clientId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);loom_capture_request_ip($clientId,'');
try{
  if($action==='status'){$g=loom_guest_ensure_for_client($clientId);$auth=loom_auth_user();$uid=(string)($auth['user_id']??$auth['userId']??'');json_out(['ok'=>true,'guest'=>loom_guest_public($g),'attachedGuests'=>$uid!==''?loom_guest_list_for_user($uid):[]]);}
  if($action==='issue-recovery'){loom_global_profile_assert_mutation_access($clientId);$g=loom_guest_ensure_for_client($clientId);json_out(['ok'=>true]+loom_guest_issue_recovery_code($g['guestId'],'self'));}
  if($action==='recover'){$code=(string)($body['recoveryCode']??'');json_out(['ok'=>true,'guest'=>loom_guest_recover_to_client($clientId,$code),'message'=>'Guest history recovered on this device.']);}
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}json_out(['ok'=>false,'error'=>'Unknown action'],400);
