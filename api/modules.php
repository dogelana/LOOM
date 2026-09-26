<?php
// @loom-file release=0.15.64 revision=14 policy=package-priority
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';
require_once __DIR__.'/_project_release_updates.php';

$project=$_GET['project']??'';
$dir=project_dir($project);
if(!$dir)json_out(['error'=>'Project not found'],404);
$clientId=safe_token((string)($_GET['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,(string)$project);loom_enforce_project_access(safe_slug((string)$project),$clientId);
loom_apply_project_release_updates(safe_slug((string)$project));

/*
  LOOM module ordering baseline
  -----------------------------
  The bootstrap loader alone owns an absolute pre-layout slot. Normal project
  modules receive their final page order from loom_apply_project_module_positioning(),
  where explicit project choices outrank soft LOOM layout defaults.
*/
function is_bootstrap_loader(array $manifest): bool { return (($manifest['module']['bootstrap']['role']??'')==='loader'); }
function effective_module_order(array $manifest): int {
  if(is_bootstrap_loader($manifest)) return -1;
  $id=(string)($manifest['action']['id']??'');
  if($id==='project.system.update-log') return 99999;
  $raw=$manifest['module']['order']??'50000';
  $n=is_numeric($raw)?intval($raw):50000;
  return max(1,$n);
}

$actionRoot=$dir.'/actions';
$modules=[];$coreProjectIds=[];

function build_runtime_module_descriptor(string $project,array $manifest,string $manifestFile,string $folder,string $allowedRoot,string $folderLabel,string $source): ?array {
  $action=$manifest['action']??null;$module=$manifest['module']??null;
  if(!is_array($action)||!is_array($module)||empty($action['id'])||empty($module['entry']))return null;
  $entry=realpath($folder.'/'.$module['entry']);$allowed=realpath($allowedRoot);
  if(!$entry||!$allowed||!str_starts_with($entry,$allowed.DIRECTORY_SEPARATOR))return null;
  $isInstance=loom_project_is_instance_owned($project)&&$source==='project';
  $urlFor=function(string $absolute)use($project,$allowed,$isInstance): string {
    if(!$isInstance)return rel_url($absolute);
    $relative=ltrim(str_replace('\\','/',substr($absolute,strlen($allowed)+1)),'/');
    $segments=array_map('rawurlencode',array_values(array_filter(explode('/',$relative),fn($x)=>$x!=='')));
    return web_base_path().'/api/project-file/'.rawurlencode(safe_slug($project)).'/'.implode('/',$segments);
  };
  $styles=[];
  foreach(($module['styles']??[]) as $style){$sp=realpath($folder.'/'.$style);if($sp&&str_starts_with($sp,$allowed.DIRECTORY_SEPARATOR))$styles[]=$urlFor($sp);}
  $fingerParts=[(string)filemtime($manifestFile),(string)filemtime($entry),hash_file('sha1',$manifestFile)?:'',hash_file('sha1',$entry)?:'',is_file(loom_admin_settings_file($project))?(hash_file('sha1',loom_admin_settings_file($project))?:''):'' ];
  foreach($styles as $url){$abs=root_dir().substr($url,strlen(web_base_path()));if(is_file($abs))$fingerParts[]=hash_file('sha1',$abs)?:'';}
  $effectiveOrder=effective_module_order($manifest);
  $presentation=is_array($manifest['presentation']??null)?$manifest['presentation']:[];
  if(!is_array($presentation['responsive']??null))$presentation['responsive']=[];
  $defaultHideOnMobile=(bool)($presentation['responsive']['hideOnMobile']??false);
  $presentation['responsive']['hideOnMobile']=loom_project_module_hide_on_mobile($project,(string)$action['id'],$defaultHideOnMobile);
  $presentation=loom_apply_project_module_presentation($project,(string)$action['id'],$presentation);
  return [
    'schema_version'=>$manifest['schema_version']??'1.0','enabled'=>true,'action'=>$action,'user_actions'=>array_values($manifest['user_actions']??[]),'module'=>$module,
    'config'=>loom_module_config_with_admin_overrides($project,$manifest),'admin_overrides'=>loom_module_admin_overrides($project,(string)$action['id']),'admin_settings'=>$manifest['admin_settings']??new stdClass(),
    'extensions'=>$manifest['extensions']??new stdClass(),'capabilities'=>$manifest['capabilities']??['provides'=>[],'requires'=>[],'permissions'=>[]],'presentation'=>$presentation,'pegboard'=>$manifest['pegboard']??new stdClass(),
    'order_effective'=>$effectiveOrder,'order_display'=>is_bootstrap_loader($manifest)?'BOOT':str_pad((string)$effectiveOrder,5,'0',STR_PAD_LEFT),
    'order_locked'=>(bool)($module['order_locked']??false)||is_bootstrap_loader($manifest),
    'bootstrap'=>$manifest['module']['bootstrap']??new stdClass(),'entry_url'=>$urlFor($entry),'styles'=>$styles,'manifest_url'=>$urlFor($manifestFile),
    'fingerprint'=>substr(hash('sha256',implode('|',$fingerParts)),0,16),'folder'=>$folderLabel,'source'=>$source
  ];
}

// LOOM-owned project-scoped core modules are physically global but receive
// independent per-project Admin overrides and run inside every project.
foreach(loom_core_module_records('project') as $record){
  $manifest=loom_project_core_manifest_for_project(safe_slug((string)$project),$record['manifest']);$folder=$record['folder'];$coreId=(string)($manifest['action']['id']??'');if($coreId!==''&&!loom_project_module_enabled(safe_slug((string)$project),$coreId,(bool)($manifest['enabled']??true)))continue;
  $descriptor=build_runtime_module_descriptor($project,$manifest,$record['manifestFile'],$folder,$folder,'core-modules/'.basename($folder),'core-project');
  if($descriptor){$modules[]=$descriptor;$coreProjectIds[(string)$manifest['action']['id']]=true;}
}

// HTML Framer frames are installation-owned packages exposed as first-class
// dynamic LOOM modules. Disabling the manager suppresses all frames without deleting them.
if(loom_project_module_enabled(safe_slug((string)$project),'loom.html-framer',true)){
  foreach(loom_html_framer_runtime_descriptors(safe_slug((string)$project),$clientId) as $htmlFrameDescriptor){$hid=(string)($htmlFrameDescriptor['action']['id']??'');if($hid!==''&&!loom_project_module_enabled(safe_slug((string)$project),$hid,true))continue;$modules[]=$htmlFrameDescriptor;}
}

if(is_dir($actionRoot)){
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($actionRoot,FilesystemIterator::SKIP_DOTS));
  foreach($it as $file){
    if(!$file->isFile() || strtolower($file->getFilename())!=='manifest.json')continue;
    $manifest=read_json_file($file->getPathname());
    if(!$manifest)continue;
    $legacyId=(string)($manifest['action']['id']??'');
    if($legacyId!==''&&!loom_project_module_enabled(safe_slug((string)$project),$legacyId,(bool)($manifest['enabled']??true)))continue;
    if(isset($coreProjectIds[$legacyId]))continue;
    if(in_array($legacyId,['core.system.update-log','core.ui.update-log','core.ui.loom-update-log'],true))continue;
    $folder=$file->getPath();$descriptor=build_runtime_module_descriptor($project,$manifest,$file->getPathname(),$folder,$dir,str_replace('\\','/',substr($folder,strlen($dir)+1)),'project');
    if($descriptor)$modules[]=$descriptor;
  }
}

$modules=loom_apply_project_module_positioning(safe_slug((string)$project),$modules);$modules=loom_capability_contract_status(safe_slug($project),$modules);$registryEtag='"'.substr(hash('sha256',json_encode(array_map(fn($m)=>[$m['action']['id']??'', $m['fingerprint']??'', $m['config']??[], $m['capabilities']??[]],$modules))),0,24).'"';header('ETag: '.$registryEtag);if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$registryEtag){http_response_code(304);exit;}
json_out([
  'project'=>safe_slug($project),
  'generated_at'=>gmdate('c'),'registry_etag'=>$registryEtag,
  'discovery'=>[
    'source'=>'filesystem-scan',
    'action_root'=>'projects/'.safe_slug($project).'/actions + core-modules(scope=project)',
    'count'=>count($modules)
  ],
  'modules'=>$modules
]);
