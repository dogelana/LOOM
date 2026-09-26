<?php
// @loom-file release=0.15.60 revision=7 policy=package-priority
require __DIR__.'/_common.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';
function loom_global_profile_api_public(array $p,string $clientId): array { $visible=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name($p):loom_clean_username((string)($p['displayName']??''));if($visible==='')$visible=function_exists('loom_guest_profile_visible_name_for_client')?loom_guest_profile_visible_name_for_client($clientId):'';$internal=(string)($p['username']??'');$p['displayName']=$visible!==''?$visible:'LOOM User';$p['username']=$p['displayName'];$p['internalHandle']=$internal?:null;return $p; }
if($method==='GET'){$clientId=safe_token((string)($_GET['clientId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);$p=loom_global_profile_ensure($clientId);$a=loom_global_avatar_state($clientId);json_out(['ok'=>true,'profile'=>loom_global_profile_api_public($p,$clientId),'avatar'=>$a]);}
if($method!=='POST')json_out(['ok'=>false,'error'=>'GET or POST required'],405);
$ct=(string)($_SERVER['CONTENT_TYPE']??'');
if(str_contains($ct,'multipart/form-data')){$clientId=safe_token((string)($_POST['clientId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);if(!isset($_FILES['avatar']))json_out(['ok'=>false,'error'=>'No avatar received'],400);$f=$_FILES['avatar'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_out(['ok'=>false,'error'=>'Upload failed'],400);$fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file((string)$f['tmp_name']);try{$a=loom_global_avatar_store($clientId,(string)$f['tmp_name'],$mime,(int)($f['size']??0));json_out(['ok'=>true,'profile'=>loom_global_profile_api_public(loom_global_profile_ensure($clientId),$clientId),'avatar'=>$a]);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}}
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);$clientId=safe_token((string)($body['clientId']??''));if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);$action=(string)($body['action']??'');try{
  $sourceMeta=null;
  if($action==='set-username')$p=loom_global_profile_set_username($clientId,(string)($body['username']??''));
  elseif($action==='set-avatar-mode'){loom_global_avatar_set_mode($clientId,(string)($body['mode']??''));$p=loom_global_profile_ensure($clientId);}
  elseif($action==='copy-project-avatar'){
    loom_global_profile_assert_mutation_access($clientId);$project=safe_slug((string)($body['project']??''));if($project===''||!project_dir($project))throw new RuntimeException('Choose a valid project.');$src=loom_avatar_effective_source($clientId,$project);$sourceMeta=['project'=>$project,'kind'=>$src['kind']??'unknown'];
    if(($src['kind']??'')==='loom-default'){loom_global_avatar_set_mode($clientId,'auto');$p=loom_global_profile_ensure($clientId);}
    elseif(($src['kind']??'')==='global-custom'){loom_global_avatar_set_mode($clientId,'custom');$p=loom_global_profile_ensure($clientId);}
    else{loom_global_avatar_copy_trusted_source($clientId,(string)$src['file'],(string)$src['mime'],(int)$src['size']);$p=loom_global_profile_ensure($clientId);}
  }
  else json_out(['ok'=>false,'error'=>'Unknown action'],400);
  json_out(['ok'=>true,'profile'=>loom_global_profile_api_public($p,$clientId),'avatar'=>loom_global_avatar_state($clientId),'source'=>$sourceMeta]);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],409);}
