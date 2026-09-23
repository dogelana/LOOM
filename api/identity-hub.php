<?php
// @loom-file release=0.12.08 revision=1 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$installationId=loom_guest_installation_id((string)($body['installationId']??''));if($installationId==='')json_out(['ok'=>false,'error'=>'Invalid installation identity'],400);
try{
  if($action==='status'){
    $legacy=safe_token((string)($body['legacyClientId']??''));$profiles=loom_guest_profiles_for_installation($installationId);if(!$profiles&&$legacy!=='')loom_guest_profile_adopt_legacy($installationId,$legacy);$auth=loom_auth_user();
    json_out(['ok'=>true,'installationId'=>$installationId,'profiles'=>loom_guest_profiles_for_installation($installationId),'authenticated'=>(bool)$auth,'account'=>$auth?['userId'=>$auth['user_id']??$auth['userId']??null,'email'=>$auth['email']??null,'privilege'=>$auth['privilege']??'User']:null,'avatarPresets'=>array_map(fn($i)=>['id'=>sprintf('preset-%02d',$i),'url'=>web_base_path().'/assets/avatars/defaults/avatar-'.sprintf('%02d',$i).'.svg'],range(1,10))]);
  }
  if($action==='create')json_out(['ok'=>true,'profile'=>loom_guest_profile_create($installationId,(string)($body['displayName']??''),(string)($body['avatarPreset']??'preset-01'))]);
  if($action==='select'){$pid=safe_token((string)($body['guestProfileId']??''));$profiles=loom_guest_profiles_for_installation($installationId);$found=null;foreach($profiles as $p)if(($p['guestProfileId']??'')===$pid)$found=$p;if(!$found)throw new RuntimeException('Guest profile is not available on this installation.');json_out(['ok'=>true,'profile'=>$found]);}
  if($action==='update')json_out(['ok'=>true,'profile'=>loom_guest_profile_update($installationId,(string)($body['guestProfileId']??''),array_key_exists('displayName',$body)?(string)$body['displayName']:null,array_key_exists('avatarPreset',$body)?(string)$body['avatarPreset']:null)]);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}json_out(['ok'=>false,'error'=>'Unknown action'],400);
