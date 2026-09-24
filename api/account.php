<?php
// @loom-file release=0.15.02 revision=4 policy=package-priority
require __DIR__.'/_common.php';
$method=$_SERVER['REQUEST_METHOD']??'POST';if($method!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);$action=(string)($body['action']??'status');$clientId=safe_token((string)($body['clientId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);$project=safe_slug((string)($body['project']??''));if($project!==''&&!project_dir($project))json_out(['ok'=>false,'error'=>'Invalid project'],400);loom_capture_request_ip($clientId,$project);
if($action==='status'){$priv=loom_bootstrap_or_privilege($clientId,false);$g=loom_guest_ensure_for_client($clientId);json_out(['ok'=>true,'account'=>loom_account_public_status($clientId,$project),'guest'=>loom_guest_public($g),'privilege'=>$priv,'storageMode'=>loom_db_ready()?'database':'durable-local']);}
if($action==='register'){
  $global=loom_global_profile_ensure($clientId);
  $identity=$project!==''?loom_project_identity_ensure($project,$clientId,true):null;
  $username=loom_clean_username((string)($identity['effectiveUsername']??$global['username']??''));
  if($username==='')json_out(['ok'=>false,'error'=>'Set a LOOM username first. A permanent account cannot be created without one.'],409);
  if(loom_account_user_for_client($clientId))json_out(['ok'=>false,'error'=>'This client is already linked to an account. Sign in or log out first.'],409);
  $email=trim((string)($body['email']??''));$password=(string)($body['password']??'');$priv=loom_client_is_admin($clientId)?'Admin':'User';
  try{
    $user=loom_register_account($clientId,$username,$email,$password,$priv);
    $uid=(string)($user['user_id']??$user['userId']??'');
    loom_link_admin_to_user_if_applicable($clientId,$uid);
    loom_reconcile_admin_identity($clientId);
    $effective=loom_client_is_admin($clientId)?'Admin':'User';
    $identity=$project!==''?loom_project_identity_ensure($project,$clientId,true):null;
    $account=loom_account_public_status($clientId,$project);$generation=function_exists('loom_guest_profile_claim_generation')?loom_guest_profile_claim_generation($clientId,$uid,'register'):null;loom_audit_record('account.register',['clientId'=>$clientId,'userId'=>$uid,'project'=>$project,'details'=>['guestGeneration'=>$generation]],'Permanent LOOM account created and current guest payload claimed.');
    json_out([
      'ok'=>true,
      'message'=>$project!==''?'Permanent account created. Your LOOM profile is global; this project may inherit or override its username/profile picture.':'Permanent LOOM account created.',
      'account'=>$account,
      'guest'=>loom_guest_public(loom_guest_ensure_for_client($clientId)),
      'attachedGuestHistories'=>$uid!==''?loom_guest_list_for_user($uid):[],'guestGeneration'=>$generation,
      'projectIdentity'=>$identity,
      'privilege'=>['privilege'=>$effective,'isAdmin'=>$effective==='Admin','bootstrapped'=>false]
    ]);
  }catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}
}
if($action==='login'){$email=trim((string)($body['email']??''));$password=(string)($body['password']??'');try{$user=loom_login_account($clientId,$email,$password);$uid=(string)($user['user_id']??$user['userId']??'');loom_reconcile_admin_identity($clientId);$identity=$project!==''?loom_project_identity_ensure($project,$clientId,true):null;$generation=function_exists('loom_guest_profile_claim_generation')?loom_guest_profile_claim_generation($clientId,$uid,'login'):null;$effective=loom_client_is_admin($clientId)?'Admin':'User';loom_audit_record('account.login',['clientId'=>$clientId,'userId'=>$uid,'project'=>$project,'details'=>['guestGeneration'=>$generation]],'Permanent account signed in; eligible guest payload claimed automatically.');json_out(['ok'=>true,'message'=>'Signed in. Current guest payload was claimed automatically when eligible.','account'=>loom_account_public_status($clientId,$project),'guest'=>loom_guest_public(loom_guest_ensure_for_client($clientId)),'guestGeneration'=>$generation,'attachedGuestHistories'=>$uid!==''?loom_guest_list_for_user($uid):[],'projectIdentity'=>$identity,'privilege'=>['privilege'=>$effective,'isAdmin'=>$effective==='Admin','bootstrapped'=>false]]);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],401);}}
if($action==='logout'){$resume=function_exists('loom_guest_profile_resume_client_for_claimed_client')?loom_guest_profile_resume_client_for_claimed_client($clientId):null;loom_revoke_auth_session();loom_audit_record('account.logout',['clientId'=>$clientId,'project'=>$project,'details'=>['resumeGuestClientId'=>$resume]],'Permanent account signed out; guest profile can resume with a fresh payload generation.');json_out(['ok'=>true,'message'=>'Signed out.','resumeGuestClientId'=>$resume,'account'=>loom_account_public_status($clientId,$project),'privilege'=>loom_bootstrap_or_privilege($clientId,false)]);}json_out(['ok'=>false,'error'=>'Unknown account action'],400);
