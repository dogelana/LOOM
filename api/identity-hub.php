<?php
// @loom-file release=0.15.47 revision=3 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$installationId=loom_guest_installation_id((string)($body['installationId']??''));if($installationId==='')json_out(['ok'=>false,'error'=>'Invalid installation identity'],400);
try{
  if($action==='status'){
    $legacy=safe_token((string)($body['legacyClientId']??''));$signals=is_array($body['continuitySignals']??null)?$body['continuitySignals']:[];$profiles=loom_guest_profiles_for_installation($installationId);$continuity=['matched'=>false,'reason'=>'not-needed'];
    if(!$profiles&&$legacy!==''){
      // A client already known by the server keeps its explicit lineage. A truly
      // fresh browser may instead attempt conservative Continuity recovery.
      if(loom_guest_profile_for_client($legacy)||loom_guest_for_client($legacy))loom_guest_profile_adopt_legacy($installationId,$legacy);
      else $continuity=loom_continuity_match($installationId,$legacy,$signals);
    }
    $auth=loom_auth_user();
    json_out(['ok'=>true,'installationId'=>$installationId,'profiles'=>loom_guest_profiles_for_installation($installationId),'authenticated'=>(bool)$auth,'account'=>$auth?['userId'=>$auth['user_id']??$auth['userId']??null,'email'=>$auth['email']??null,'privilege'=>$auth['privilege']??'User']:null,'continuity'=>$continuity,'avatarPresets'=>[['id'=>'loom-default','url'=>web_base_path().'/assets/loom-default-avatar.svg']]]);
  }
  if($action==='create'){
    $preferred=safe_token((string)($body['clientId']??''));$profile=loom_guest_profile_create($installationId,(string)($body['displayName']??''),(string)($body['avatarPreset']??'loom-default'),$preferred?:null);
    if(is_array($body['continuitySignals']??null))loom_continuity_observe($installationId,(string)($profile['currentClientId']??''),(string)($profile['guestProfileId']??''),(array)$body['continuitySignals'],'created');
    json_out(['ok'=>true,'profile'=>$profile]);
  }
  if($action==='select'){$pid=safe_token((string)($body['guestProfileId']??''));$profiles=loom_guest_profiles_for_installation($installationId);$found=null;foreach($profiles as $p)if(($p['guestProfileId']??'')===$pid)$found=$p;if(!$found)throw new RuntimeException('Guest profile is not available on this installation.');json_out(['ok'=>true,'profile'=>$found]);}
  if($action==='update')json_out(['ok'=>true,'profile'=>loom_guest_profile_update($installationId,(string)($body['guestProfileId']??''),array_key_exists('displayName',$body)?(string)$body['displayName']:null,array_key_exists('avatarPreset',$body)?(string)$body['avatarPreset']:null)]);
  if($action==='continuity-observe'){
    $clientId=safe_token((string)($body['clientId']??''));$profileId=safe_token((string)($body['guestProfileId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))throw new RuntimeException('Invalid continuity identity.');
    json_out(['ok'=>true,'continuity'=>loom_continuity_observe($installationId,$clientId,$profileId,is_array($body['continuitySignals']??null)?$body['continuitySignals']:[],'active')]);
  }
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}json_out(['ok'=>false,'error'=>'Unknown action'],400);
