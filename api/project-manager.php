<?php
// @loom-file release=0.15.08 revision=6 policy=package-priority
require __DIR__.'/_common.php';
if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=$_POST;
$action=(string)($body['action']??'');

function loom_project_manager_profile_input(array $body): array {
  $profile=$body['profile']??[];return is_array($profile)?$profile:[];
}

if($action==='create'){
  $slug=safe_slug((string)($body['slug']??''));$profile=loom_project_manager_profile_input($body);
  if($slug==='')json_out(['ok'=>false,'error'=>'invalid-slug'],400);
  if(project_exists_anywhere($slug))json_out(['ok'=>false,'error'=>'project-slug-already-exists'],409);
  if(!install_project_template('baseline',$slug))json_out(['ok'=>false,'error'=>'baseline-template-install-failed'],500);
  $dir=project_dir($slug);if(!$dir)json_out(['ok'=>false,'error'=>'project-create-verification-failed'],500);
  $file=$dir.'/project.default.json';$data=read_json_file($file)?:[];$data['slug']=$slug;$data['project_generation']='instance-baseline-v1';$data['engine_version']=loom_release_version();
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
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
    $saved=loom_write_project_profile($slug,$profile);
    $logo=(string)($body['logoPngBase64']??'');if($logo!=='')$saved=loom_save_project_logo($slug,$logo);
  }catch(RuntimeException $e){recursive_remove($dir);json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'action'=>'create','slug'=>$slug,'source'=>'instance','app_url'=>loom_project_public_url($slug),'canonical_app_url'=>loom_project_app_url($slug),'profile'=>$saved]);
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
