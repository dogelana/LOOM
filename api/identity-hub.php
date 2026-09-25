<?php
// @loom-file release=0.15.55 revision=5 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$installationId=loom_guest_installation_id((string)($body['installationId']??''));if($installationId==='')json_out(['ok'=>false,'error'=>'Invalid installation identity'],400);
$signals=is_array($body['continuitySignals']??null)?$body['continuitySignals']:[];
function loom_identity_hub_permanent_required(array $policy): never { json_out(['ok'=>false,'code'=>'PERMANENT_ACCOUNT_REQUIRED','error'=>$policy['message']??'Permanent LOOM account required.','guestPolicy'=>$policy],409); }
try{
  if($action==='status'){
    $legacy=safe_token((string)($body['legacyClientId']??''));if($legacy!=='')loom_enforce_global_access($legacy);$profiles=loom_guest_profiles_for_installation($installationId);
    // Explicit legacy lineage is preserved. Cross-browser anonymous auto-resume is
    // intentionally disabled by the strict ambiguity policy: if another guest could
    // plausibly be this environment, permanent authentication is required instead.
    if(!$profiles&&$legacy!==''&&(loom_guest_profile_for_client($legacy)||loom_guest_for_client($legacy)))loom_guest_profile_adopt_legacy($installationId,$legacy);
    $auth=loom_auth_user();$policy=loom_continuity_guest_mode_policy($installationId,$legacy,$signals,true,false);
    json_out(['ok'=>true,'installationId'=>$installationId,'profiles'=>loom_guest_profiles_for_installation($installationId),'authenticated'=>(bool)$auth,'account'=>$auth?['userId'=>$auth['user_id']??$auth['userId']??null,'email'=>$auth['email']??null,'privilege'=>$auth['privilege']??'User']:null,'continuity'=>['matched'=>false,'reason'=>'strict-permanent-on-overlap'],'guestPolicy'=>$policy,'avatarPresets'=>[['id'=>'loom-default','url'=>web_base_path().'/assets/loom-default-avatar.svg']]]);
  }
  if($action==='create'){
    $preferred=safe_token((string)($body['clientId']??''));if($preferred!=='')loom_enforce_global_access($preferred);if($preferred==='')throw new RuntimeException('Invalid client identity.');$policy=loom_continuity_guest_mode_policy($installationId,$preferred,$signals,true,true);if(!empty($policy['permanentRequired']))loom_identity_hub_permanent_required($policy);
    $profile=loom_guest_profile_create($installationId,(string)($body['displayName']??''),(string)($body['avatarPreset']??'loom-default'),$preferred);
    $observed=loom_continuity_observe($installationId,(string)($profile['currentClientId']??''),(string)($profile['guestProfileId']??''),$signals,'created');
    $after=loom_continuity_guest_mode_policy($installationId,(string)($profile['currentClientId']??''),$signals,true,false);
    if(!empty($after['permanentRequired']))loom_identity_hub_permanent_required($after);
    json_out(['ok'=>true,'profile'=>$profile,'continuity'=>$observed,'guestPolicy'=>$after]);
  }
  if($action==='select'){
    $pid=safe_token((string)($body['guestProfileId']??''));$profiles=loom_guest_profiles_for_installation($installationId);$found=null;foreach($profiles as $p)if(($p['guestProfileId']??'')===$pid)$found=$p;if(!$found)throw new RuntimeException('Guest profile is not available on this installation.');$cid=safe_token((string)($found['currentClientId']??''));if($cid!=='')loom_enforce_global_access($cid);$policy=loom_continuity_guest_mode_policy($installationId,$cid,$signals,true,false);if(!empty($policy['permanentRequired']))loom_identity_hub_permanent_required($policy);json_out(['ok'=>true,'profile'=>$found,'guestPolicy'=>$policy]);
  }
  if($action==='update'){
    $pid=safe_token((string)($body['guestProfileId']??''));$existing=loom_guest_profile_get($pid);$cid=safe_token((string)($existing['generations'][(string)($existing['currentGeneration']??1)]['clientId']??''));if($cid!=='')loom_enforce_global_access($cid);$policy=loom_continuity_guest_mode_policy($installationId,$cid,$signals,true,false);if(!empty($policy['permanentRequired']))loom_identity_hub_permanent_required($policy);json_out(['ok'=>true,'profile'=>loom_guest_profile_update($installationId,$pid,array_key_exists('displayName',$body)?(string)$body['displayName']:null,array_key_exists('avatarPreset',$body)?(string)$body['avatarPreset']:null),'guestPolicy'=>$policy]);
  }
  if($action==='continuity-observe'){
    $clientId=safe_token((string)($body['clientId']??''));$profileId=safe_token((string)($body['guestProfileId']??''));if($clientId!=='' )loom_enforce_global_access($clientId);if($clientId===''||!str_starts_with($clientId,'client_'))throw new RuntimeException('Invalid continuity identity.');$continuity=loom_continuity_observe($installationId,$clientId,$profileId,$signals,'active');$policy=loom_continuity_guest_mode_policy($installationId,$clientId,$signals,true,false);json_out(['ok'=>true,'continuity'=>$continuity,'guestPolicy'=>$policy]);
  }
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}json_out(['ok'=>false,'error'=>'Unknown action'],400);
