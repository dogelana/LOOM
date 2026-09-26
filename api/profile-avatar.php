<?php
// @loom-file release=0.15.62 revision=7 policy=package-priority
require __DIR__.'/_common.php';

function loom_profile_avatar_default_source(): array {
  $file=root_dir().'/assets/loom-default-avatar.svg';
  return ['kind'=>'loom-default','file'=>$file,'mime'=>'image/svg+xml','size'=>is_file($file)?(int)filesize($file):0];
}
function loom_profile_avatar_preset_source(string $mode): ?array {
  $mode=loom_global_avatar_preset_normalize($mode)??'';if($mode==='')return null;
  $file=root_dir().'/assets/avatars/defaults/avatar-'.substr($mode,-2).'.svg';
  return is_file($file)?['kind'=>'global-preset','file'=>$file,'mime'=>'image/svg+xml','size'=>(int)filesize($file)]:null;
}
function loom_profile_avatar_context(string $subjectType,string $subjectId,string $clientId): array {
  $subjectType=strtolower(trim($subjectType));
  if(in_array($subjectType,['permanent','account','permanent-account'],true))$subjectType='user';
  if(in_array($subjectType,['guest-identity','guest_identity'],true))$subjectType='guest';
  $subjectId=safe_token($subjectId);$clientId=safe_token($clientId);
  if($clientId!==''&&!str_starts_with($clientId,'client_'))$clientId='';

  if($subjectType==='user'&&$subjectId!==''){
    // A migration fallback may consult a legacy client avatar, but only when the
    // supplied client is provably attached to this permanent account. Never let
    // an arbitrary clientId borrow another identity's protected avatar.
    if($clientId!==''){
      $clientValid=false;
      $linked=loom_account_user_for_client($clientId);
      if(is_array($linked)&&safe_token((string)($linked['userId']??$linked['user_id']??$linked['id']??''))===$subjectId)$clientValid=true;
      if(!$clientValid){
        foreach(loom_guest_list_for_user($subjectId) as $guest){
          $guest=loom_guest_root($guest);$clients=array_keys((array)($guest['clients']??[]));$primary=safe_token((string)($guest['primaryClientId']??''));
          if($clientId===$primary||in_array($clientId,$clients,true)){$clientValid=true;break;}
        }
      }
      if(!$clientValid)$clientId='';
    }
    return ['subjectType'=>'user','subjectId'=>$subjectId,'ownerType'=>'user','ownerId'=>$subjectId,'clientId'=>$clientId];
  }
  if($subjectType==='guest'&&$subjectId!==''){
    $g=loom_guest_get($subjectId);if(!$g)throw new RuntimeException('Guest Identity not found.');$g=loom_guest_root($g);
    $subjectId=safe_token((string)($g['guestId']??$subjectId));$clients=array_keys((array)($g['clients']??[]));$primary=safe_token((string)($g['primaryClientId']??''));
    if($clientId===''||(!in_array($clientId,$clients,true)&&$clientId!==$primary))$clientId=$primary;
    if($clientId==='')throw new RuntimeException('Guest Identity has no canonical browser client.');
    $ownerId=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($clientId):$clientId;
    return ['subjectType'=>'guest','subjectId'=>$subjectId,'ownerType'=>'client','ownerId'=>$ownerId,'clientId'=>$clientId];
  }
  if($clientId!==''){
    $owner=loom_global_profile_owner_for_client($clientId);
    return ['subjectType'=>$owner['type']==='user'?'user':'client','subjectId'=>(string)$owner['id'],'ownerType'=>(string)$owner['type'],'ownerId'=>(string)$owner['id'],'clientId'=>$clientId];
  }
  throw new RuntimeException('Invalid avatar identity.');
}
function loom_profile_avatar_global_source(array $ctx): array {
  $type=(string)$ctx['ownerType'];$id=(string)$ctx['ownerId'];$clientId=(string)($ctx['clientId']??'');
  $profile=loom_global_profile_get($type,$id);$mode=(string)($profile['avatarMode']??'');
  if($mode==='loom-default')$mode=loom_global_avatar_repair_preset($type,$id);
  if($preset=loom_profile_avatar_preset_source($mode))return $preset;

  $custom=loom_global_avatar_find($type,$id);
  if($custom&&($mode==='custom'||$mode===''||$mode==='auto'))return ['kind'=>'global-custom']+$custom;
  if($mode==='custom'||$mode===''||$mode==='auto'){
    $legacy=loom_avatar_legacy_find_file($type,$id);if($legacy)return ['kind'=>'legacy-global-custom']+$legacy;
    // In incomplete client→user migrations, the account can be canonical while
    // the last custom image still lives under a proven linked client. Read it,
    // but never expose the protected path.
    if($type==='user'&&$clientId!==''){
      $clientCustom=loom_global_avatar_find('client',$clientId);if($clientCustom)return ['kind'=>'legacy-client-global-custom']+$clientCustom;
      $clientLegacy=loom_avatar_legacy_find_file('client',$clientId);if($clientLegacy)return ['kind'=>'legacy-client-avatar']+$clientLegacy;
    }
  }
  return loom_profile_avatar_default_source();
}
function loom_profile_avatar_project_source(array $ctx,string $project): array {
  $project=safe_slug($project);if($project===''||!project_dir($project))throw new RuntimeException('Invalid project.');
  $type=(string)$ctx['ownerType'];$id=(string)$ctx['ownerId'];$clientId=safe_token((string)($ctx['clientId']??''));$identity=loom_project_identity_get_for_owner($project,$type,$id);
  // Older project identities/images may still be keyed to a proven linked client.
  // If the canonical owner has not yet absorbed that record, use only the
  // explicitly validated client supplied by the canonical user/guest request.
  $legacyIdentity=null;if($clientId!==''&&($type!=='client'||$id!==$clientId))$legacyIdentity=loom_project_identity_get_for_owner($project,'client',$clientId);
  if(!$identity&&$legacyIdentity){$identity=$legacyIdentity;$type='client';$id=$clientId;}
  $mode=(string)($identity['avatarMode']??'auto');
  if($mode==='loom-default')$mode='global';if(!in_array($mode,['auto','global','project-default','custom'],true))$mode='auto';
  if($mode==='custom'){
    $custom=loom_avatar_find_file($project,$type,$id);if($custom)return ['kind'=>$type==='client'?'legacy-linked-project-custom':'project-custom']+$custom;
    if($clientId!==''&&($type!=='client'||$id!==$clientId)){$legacy=loom_avatar_find_file($project,'client',$clientId);if($legacy)return ['kind'=>'legacy-linked-project-custom']+$legacy;}
    $mode='auto'; // incomplete/stale metadata: resolve safely rather than 404.
  }
  if(in_array($mode,['auto','project-default'],true)){
    $provider=loom_avatar_project_provider_default_file($project);if($provider)return $provider;
    if($mode==='project-default')return loom_profile_avatar_default_source();
  }
  if(in_array($mode,['auto','global'],true))return loom_profile_avatar_global_source($ctx);
  return loom_profile_avatar_global_source($ctx);
}
function loom_profile_avatar_serve(array $source): void {
  $file=(string)($source['file']??'');$mime=(string)($source['mime']??'application/octet-stream');
  if($file===''||!is_file($file)){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Avatar unavailable';exit;}
  $etag='"'.file_cache_version($file).'"';
  header('X-Content-Type-Options: nosniff');header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($file));header('Cache-Control: private, max-age=86400, stale-while-revalidate=604800');header('ETag: '.$etag);
  if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
  readfile($file);exit;
}

$method=$_SERVER['REQUEST_METHOD']??'GET';$action=(string)($_GET['action']??'state');
if($method==='GET'){
  $clientId=safe_token((string)($_GET['clientId']??''));$project=safe_slug((string)($_GET['project']??''));$scope=strtolower((string)($_GET['scope']??'project'));
  $subjectType=(string)($_GET['subjectType']??'');$subjectId=safe_token((string)($_GET['subjectId']??''));
  if($action==='image'){
    try{$ctx=loom_profile_avatar_context($subjectType,$subjectId,$clientId);$source=$scope==='global'?loom_profile_avatar_global_source($ctx):loom_profile_avatar_project_source($ctx,$project);loom_profile_avatar_serve($source);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  }
  if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);
  if($project===''||!project_dir($project))json_out(['ok'=>false,'error'=>'Invalid project'],400);
  json_out(['ok'=>true]+loom_avatar_state($clientId,$project));
}
if($method!=='POST')json_out(['ok'=>false,'error'=>'GET or POST required'],405);
$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
if(str_contains($contentType,'multipart/form-data')){
  $clientId=safe_token((string)($_POST['clientId']??''));$project=safe_slug((string)($_POST['project']??''));$action=(string)($_POST['action']??'upload');
  if($action!=='upload')json_out(['ok'=>false,'error'=>'Invalid multipart action'],400);
  if($clientId===''||!str_starts_with($clientId,'client_')||$project===''||!project_dir($project))json_out(['ok'=>false,'error'=>'Invalid project identity'],400);
  try{loom_avatar_assert_mutation_access($clientId);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],403);}
  if(!isset($_FILES['avatar'])||!is_array($_FILES['avatar']))json_out(['ok'=>false,'error'=>'No avatar file received'],400);$f=$_FILES['avatar'];
  if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_out(['ok'=>false,'error'=>'Avatar upload failed'],400);
  $tmp=(string)$f['tmp_name'];$bytes=(int)($f['size']??0);$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($tmp);
  try{$state=loom_avatar_store_file($clientId,$project,$tmp,$mime,$bytes);json_out(['ok'=>true]+$state);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
}
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$clientId=safe_token((string)($body['clientId']??''));$project=safe_slug((string)($body['project']??''));$action=(string)($body['action']??'');
if($clientId===''||!str_starts_with($clientId,'client_')||$project===''||!project_dir($project))json_out(['ok'=>false,'error'=>'Invalid project identity'],400);
if($action==='set-mode'){
  try{$state=loom_avatar_set_mode($clientId,$project,(string)($body['mode']??'auto'));json_out(['ok'=>true]+$state);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
}
json_out(['ok'=>false,'error'=>'Unknown avatar action'],400);
