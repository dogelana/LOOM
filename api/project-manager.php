<?php
// @loom-file release=0.15.72 revision=10 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=$_POST;
$requestClientId=safe_token((string)($body['clientId']??$_REQUEST['clientId']??''));
if(!loom_request_is_admin()&&($requestClientId===''||!loom_client_is_admin($requestClientId)))json_out(['ok'=>false,'error'=>'admin-access-required'],403);
$action=(string)($body['action']??'');

function loom_project_manager_profile_input(array $body): array {
  $profile=$body['profile']??[];return is_array($profile)?$profile:[];
}
function loom_project_manager_unique_name(string $requested): string {
  $base=trim(loom_clean_project_text($requested,140));if($base==='')return '';
  $used=[];foreach(loom_all_project_slugs() as $existingSlug){$d=loom_project_effective_data($existingSlug);$n=trim((string)($d['name']??humanize_project_slug($existingSlug)));if($n!=='')$used[strtolower($n)]=true;}
  if(!isset($used[strtolower($base)]))return $base;
  for($n=2;$n<10000;$n++){$candidate=$base.' '.$n;if(!isset($used[strtolower($candidate)]))return $candidate;}
  return $base.' '.substr(hash('sha256',microtime(true).random_bytes(8)),0,6);
}

if($action==='create'){
  $requestedSlug=safe_slug((string)($body['slug']??''));$profile=loom_project_manager_profile_input($body);
  if($requestedSlug==='')json_out(['ok'=>false,'error'=>'invalid-slug'],400);
  $slug=loom_project_unique_public_slug($requestedSlug);
  if(!install_project_template('baseline',$slug))json_out(['ok'=>false,'error'=>'baseline-template-install-failed'],500);
  $dir=project_dir($slug);if(!$dir)json_out(['ok'=>false,'error'=>'project-create-verification-failed'],500);
  $file=$dir.'/project.default.json';$data=read_json_file($file)?:[];$data['slug']=$slug;$data['project_generation']='instance-baseline-v1';$data['engine_version']=loom_release_version();
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);loom_project_effective_cache_forget($slug);
  $fallbackFile=$dir.'/registry.fallback.json';$fallback=read_json_file($fallbackFile);if(is_array($fallback)){
    $fallback['project']=$slug;
    $rewrite=function(mixed $value)use($slug,&$rewrite): mixed {
      if(is_string($value))return str_replace('./projects/baseline/','./projects/'.$slug.'/',$value);
      if(is_array($value)){foreach($value as $k=>$v)$value[$k]=$rewrite($v);return $value;}
      return $value;
    };
    $fallback=$rewrite($fallback);@file_put_contents($fallbackFile,json_encode($fallback,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."
",LOCK_EX);
  }
  try{
    if(!array_key_exists('name',$profile))$profile['name']=humanize_project_slug($slug);
    $requestedName=trim((string)($profile['name']??''));$uniqueName=loom_project_manager_unique_name($requestedName);
    if($uniqueName!=='')$profile['name']=$uniqueName;
    $saved=loom_write_project_profile($slug,$profile);
    $logo=(string)($body['logoPngBase64']??'');if($logo!=='')$saved=loom_save_project_logo($slug,$logo);
  }catch(RuntimeException $e){recursive_remove($dir);json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'action'=>'create','slug'=>$slug,'requested_slug'=>$requestedSlug,'slug_adjusted'=>$slug!==$requestedSlug,'source'=>'instance','app_url'=>loom_project_public_url($slug),'canonical_app_url'=>loom_project_app_url($slug),'profile'=>$saved]);
}

$slug=safe_slug((string)($body['slug']??''));
if($slug==='')json_out(['ok'=>false,'error'=>'invalid-slug'],400);

if($action==='profile'){
  $profile=loom_project_profile_payload($slug);if(!$profile)json_out(['ok'=>false,'error'=>'project-not-found'],404);
  json_out(['ok'=>true,'profile'=>$profile]);
}
if($action==='update-profile'){
  if(!project_dir($slug))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  try{$profile=loom_write_project_profile($slug,loom_project_manager_profile_input($body));}
  catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'action'=>'update-profile','slug'=>$slug,'profile'=>$profile]);
}
if($action==='upload-logo'){
  if(!project_dir($slug))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  try{$profile=loom_save_project_logo($slug,(string)($body['pngBase64']??''));}
  catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'action'=>'upload-logo','slug'=>$slug,'profile'=>$profile]);
}
if($action==='archive'){
  $res=archive_active_project($slug,'user-archive');
  json_out($res,($res['ok']??false)?200:409);
}
if($action==='restore'){
  $res=restore_archived_project($slug);
  json_out($res,($res['ok']??false)?200:409);
}
json_out(['ok'=>false,'error'=>'unknown-action'],400);
