<?php
// @loom-file release=0.15.00 revision=6 policy=package-priority
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';

function scan_admin_modules(string $project): array {
  $dir=project_dir($project); if(!$dir)return [];
  $out=loom_scan_project_core_modules();$coreIds=[];foreach($out as $cm)$coreIds[$cm['actionId']]=true;$root=$dir.'/actions';
  if(is_dir($root)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
      if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
      $m=read_json_file($file->getPathname());if(!$m||($m['enabled']??true)===false)continue;
      $id=(string)($m['action']['id']??'');if($id===''||isset($coreIds[$id]))continue;
      $admin=$m['admin_settings']??['fields'=>[]];
      $out[]=[
        'actionId'=>$id,
        'name'=>(string)($m['action']['name']??$id),
        'description'=>(string)($m['action']['description']??''),
        'order'=>(string)($m['module']['order']??'50000'),
        'admin_settings'=>is_array($admin)?$admin:['fields'=>[]],
        'presentation'=>is_array($m['presentation']??null)?$m['presentation']:[],
        'defaults'=>is_array($m['config']??null)?$m['config']:[],
        'source'=>'project','scope'=>'project','version'=>(string)($m['module']['version']??'0.0.0'),'manifestEnabled'=>(bool)($m['enabled']??true)
      ];
    }
  }
  $dedup=[];foreach($out as $m)$dedup[$m['actionId']]=$m;$out=array_values($dedup);
  usort($out,fn($a,$b)=>strcmp($a['order'],$b['order'])?:strcmp($a['actionId'],$b['actionId']));
  return $out;
}
function scan_project_declared_extensions(string $project): array {
  $dir=project_dir($project);if(!$dir)return [];
  $root=$dir.'/actions';$out=[];if(!is_dir($root))return $out;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
  foreach($it as $file){
    if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
    $m=read_json_file($file->getPathname());if(!$m||($m['enabled']??true)===false)continue;
    $actionId=(string)($m['action']['id']??'');
    foreach(($m['extensions']??[]) as $extensionId=>$meta){
      if(!is_array($meta))$meta=[];
      $out[(string)$extensionId]=['providerActionId'=>$actionId]+$meta;
    }
  }
  return $out;
}

function normalize_admin_value(array $field,mixed $value): mixed {
  $type=(string)($field['type']??'text');
  if($type==='range'||$type==='number'){
    if(!is_numeric($value))throw new RuntimeException('Expected numeric value');
    $n=(float)$value;
    if(isset($field['min']))$n=max((float)$field['min'],$n);
    if(isset($field['max']))$n=min((float)$field['max'],$n);
    if(($field['integer']??false)===true||$type==='range')$n=(int)round($n);
    return $n;
  }
  if($type==='checkbox'){return filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)??false;}
  if($type==='color'){
    $s=trim((string)$value);
    if(!preg_match('/^#[0-9a-fA-F]{6}$/',$s))throw new RuntimeException('Expected #RRGGBB color');
    return strtoupper($s);
  }
  if($type==='select'){
    $s=(string)$value;$allowed=[];
    foreach(($field['options']??[]) as $o)$allowed[]=is_array($o)?(string)($o['value']??''):(string)$o;
    if(!in_array($s,$allowed,true))throw new RuntimeException('Invalid selection');
    return $s;
  }
  $s=trim(preg_replace('/[\x00-\x1F\x7F]/u','',(string)$value)??'');
  $max=(int)($field['maxLength']??120);
  if(function_exists('mb_substr'))$s=mb_substr($s,0,$max,'UTF-8');else $s=substr($s,0,$max);
  return $s;
}

function loom_png_payload_to_ico(string $png): string {
  if(substr($png,0,8)!=="\x89PNG\r\n\x1a\n") throw new RuntimeException('Expected PNG favicon payload');
  $size=strlen($png);$offset=6+16;
  return pack('vvv',0,1,1).pack('CCCCvvVV',64,64,0,0,1,32,$size,$offset).$png;
}
function loom_project_asset_target(string $project,string $relative): string {
  $relative=ltrim(str_replace('\\','/',$relative),'/');
  if($relative===''||str_contains($relative,'..'))throw new RuntimeException('Invalid project asset path');
  $target=loom_project_overlay_asset($project,$relative);
  if(!$target)throw new RuntimeException('Invalid persistent project asset path');
  ensure_dir(dirname($target));
  return $target;
}

function settings_payload(string $project): array {
  $saved=loom_read_admin_settings($project);$mods=scan_admin_modules($project);$extensions=scan_project_declared_extensions($project);
  foreach($mods as &$m){
    $override=$saved['modules'][$m['actionId']]??[];
    $m['values']=array_replace_recursive($m['defaults'],is_array($override)?$override:[]);
    $m['overrides']=is_array($override)?$override:[];
    // Declarative project-derived field defaults are presentation-only here. If Admin saves
    // them they become explicit overrides, which is intentional.
    foreach(($m['admin_settings']['fields']??[]) as $field){
      $id=(string)($field['id']??'');$key=(string)($field['configKey']??$id);$from=$field['projectDefaultFromExtension']??null;
      if($id===''||$key===''||!is_array($from)||array_key_exists($key,$m['overrides']))continue;
      if((string)($m['values']['sourceMode']??'')!=='project')continue;
      $extId=(string)($from['extension']??'');$prop=(string)($from['property']??'');
      if($extId!==''&&$prop!==''&&array_key_exists($extId,$extensions)&&array_key_exists($prop,$extensions[$extId]))$m['values'][$id]=$extensions[$extId][$prop];
    }
  }unset($m);
  $catalog=[];
  foreach(loom_scan_global_core_modules() as $g){$id=$g['actionId'];$catalog[]=['actionId'=>$id,'name'=>$g['name'],'description'=>$g['description'],'scope'=>'global','source'=>'loom-core','version'=>'core','enabled'=>loom_global_module_enabled($id,true),'locked'=>false];}
  foreach($mods as $m){$id=$m['actionId'];$catalog[]=['actionId'=>$id,'name'=>$m['name'],'description'=>$m['description'],'scope'=>'project','source'=>$m['source']??'project','version'=>(string)($m['version']??'module'),'enabled'=>loom_project_module_enabled($project,$id,true),'locked'=>false];}
  foreach(loom_html_framer_public_frames($project) as $f){$id='html.frame.'.(string)$f['id'];$catalog[]=['actionId'=>$id,'name'=>(string)($f['title']??'HTML Frame'),'description'=>'HTML Framer package: '.(string)($f['zipName']??''),'scope'=>'project','source'=>'html-framer','version'=>'frame-r'.(string)($f['revision']??1),'enabled'=>loom_project_module_enabled($project,$id,(bool)($f['enabled']??true)),'locked'=>false];}
  usort($catalog,fn($a,$b)=>strcmp($a['scope'],$b['scope'])?:strcmp($a['source'],$b['source'])?:strcasecmp($a['name'],$b['name']));
  return ['project'=>$project,'project_profile'=>loom_project_profile_payload($project),'project_source'=>loom_project_source($project),'modules'=>$mods,'module_catalog'=>$catalog,'extensions'=>$extensions,'updatedAt'=>$saved['updatedAt']??null];
}

$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
  $clientId=safe_token((string)($_GET['clientId']??''));
  $status=loom_bootstrap_or_privilege($clientId,(($_GET['claim']??'0')==='1'));
  json_out(['ok'=>true]+$status);
}
if($method!=='POST')json_out(['ok'=>false,'error'=>'GET or POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$clientId=safe_token((string)($body['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,(string)($body['project']??''));
if($action==='status'){
  $status=loom_bootstrap_or_privilege($clientId,(bool)($body['claim']??false));
  json_out(['ok'=>true]+$status);
}
loom_require_admin($clientId);

if($action==='global-settings'){
  json_out(['ok'=>true,'privilege'=>'Admin']+loom_global_settings_payload());
}
if($action==='global-save'){
  $moduleId=safe_token((string)($body['moduleId']??''));$incoming=$body['values']??[];
  if($moduleId===''||!is_array($incoming))json_out(['ok'=>false,'error'=>'invalid-settings'],400);
  $target=null;foreach(loom_scan_global_core_modules() as $m)if($m['actionId']===$moduleId){$target=$m;break;}
  if(!$target)json_out(['ok'=>false,'error'=>'global-module-not-found'],404);
  $normalized=[];
  try{foreach(($target['admin_settings']['fields']??[]) as $field){if(!is_array($field))continue;$id=(string)($field['id']??'');$key=(string)($field['configKey']??$id);if($id===''||$key===''||!array_key_exists($id,$incoming))continue;$normalized[$key]=normalize_admin_value($field,$incoming[$id]);}}
  catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  $settings=loom_read_global_settings();$settings['modules'][$moduleId]=$normalized;loom_write_global_settings($settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId,'saved'=>$normalized]+loom_global_settings_payload());
}
if($action==='global-reset'){
  $moduleId=safe_token((string)($body['moduleId']??''));$settings=loom_read_global_settings();if(isset($settings['modules'][$moduleId]))unset($settings['modules'][$moduleId]);loom_write_global_settings($settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId]+loom_global_settings_payload());
}

if($action==='module-toggle'){
  $scope=(string)($body['scope']??'project');$moduleId=(string)($body['moduleId']??'');$enabled=(bool)($body['enabled']??true);
  if($moduleId==='')json_out(['ok'=>false,'error'=>'module-id-required'],400);
  if($scope==='global'){ $known=false;foreach(loom_scan_global_core_modules() as $m)if($m['actionId']===$moduleId){$known=true;break;}if(!$known)json_out(['ok'=>false,'error'=>'global-module-not-found'],404);loom_set_global_module_enabled($moduleId,$enabled);json_out(['ok'=>true,'scope'=>'global','moduleId'=>$moduleId,'enabled'=>$enabled]+loom_global_settings_payload()); }
  $toggleProject=safe_slug((string)($body['project']??''));if($toggleProject===''||!project_dir($toggleProject))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  $known=false;foreach(scan_admin_modules($toggleProject) as $m)if($m['actionId']===$moduleId){$known=true;break;}if(!$known&&str_starts_with($moduleId,'html.frame.'))foreach(loom_html_framer_public_frames($toggleProject) as $f)if($moduleId==='html.frame.'.($f['id']??'')){$known=true;break;}
  if(!$known)json_out(['ok'=>false,'error'=>'project-module-not-found'],404);loom_set_project_module_enabled($toggleProject,$moduleId,$enabled);json_out(['ok'=>true,'scope'=>'project','moduleId'=>$moduleId,'enabled'=>$enabled]+settings_payload($toggleProject));
}

$project=safe_slug((string)($body['project']??'green-beans'));
if(!$project||!project_dir($project))json_out(['ok'=>false,'error'=>'project-not-found'],404);


if($action==='project-profile-save'){
  $incoming=$body['profile']??[];if(!is_array($incoming))json_out(['ok'=>false,'error'=>'invalid-project-profile'],400);
  try{$profile=loom_write_project_profile($project,$incoming);}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Project profile saved','profile'=>$profile]+settings_payload($project));
}
if($action==='project-logo-upload'){
  try{$profile=loom_save_project_logo($project,(string)($body['pngBase64']??''));}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Project logo saved in persistent instance overlay','profile'=>$profile]+settings_payload($project));
}

if($action==='favicon-upload'){
  $moduleId=safe_token((string)($body['moduleId']??'core.ui.branding'));
  $payload=(string)($body['pngBase64']??'');
  if(str_contains($payload,','))$payload=substr($payload,strpos($payload,',')+1);
  $png=base64_decode($payload,true);if($png===false||strlen($png)<16)json_out(['ok'=>false,'error'=>'invalid-image-payload'],400);
  try{$ico=loom_png_payload_to_ico($png);$target=loom_project_asset_target($project,'assets/favicon.ico');if(@file_put_contents($target,$ico,LOCK_EX)===false)throw new RuntimeException('Could not write favicon.ico');}
  catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  $settings=loom_read_admin_settings($project);$cur=$settings['modules'][$moduleId]??[];$cur['faviconMode']='custom';$cur['customFaviconPath']='assets/favicon.ico';$settings['modules'][$moduleId]=$cur;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'message'=>'Custom favicon saved in persistent instance overlay','faviconVersion'=>file_cache_version($target)]+settings_payload($project));
}
if($action==='favicon-use-logo'){
  $moduleId=safe_token((string)($body['moduleId']??'core.ui.branding'));
  $settings=loom_read_admin_settings($project);$cur=$settings['modules'][$moduleId]??[];$cur['faviconMode']='logo';$settings['modules'][$moduleId]=$cur;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'message'=>'Favicon now follows the current project logo']+settings_payload($project));
}

if($action==='apply-preset'){
  $moduleId=safe_token((string)($body['moduleId']??''));$presetId=safe_token((string)($body['presetId']??''));
  $target=null;foreach(scan_admin_modules($project) as $m)if($m['actionId']===$moduleId){$target=$m;break;}
  if(!$target)json_out(['ok'=>false,'error'=>'module-not-found'],404);
  $preset=null;
  foreach(($target['admin_settings']['tools']??[]) as $tool){
    if(($tool['type']??'')!=='config-presets')continue;
    foreach(($tool['presets']??[]) as $candidate)if((string)($candidate['id']??'')===$presetId){$preset=$candidate;break 2;}
  }
  if(!$preset)json_out(['ok'=>false,'error'=>'preset-not-found'],404);
  $requires=(string)($preset['requiresExtension']??'');
  if($requires!==''&&!array_key_exists($requires,scan_project_declared_extensions($project)))json_out(['ok'=>false,'error'=>'required-project-extension-not-found'],400);
  $fields=$target['admin_settings']['fields']??[];$fieldMap=[];foreach($fields as $field){$id=(string)($field['id']??'');if($id!=='')$fieldMap[$id]=$field;}
  $normalized=[];
  try{
    foreach(($preset['values']??[]) as $id=>$value){
      if(!isset($fieldMap[$id]))continue;$field=$fieldMap[$id];$key=(string)($field['configKey']??$id);$normalized[$key]=normalize_admin_value($field,$value);
    }
  }catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  $settings=loom_read_admin_settings($project);
  if(($preset['reset']??false)===true)unset($settings['modules'][$moduleId]);
  if($normalized){$current=$settings['modules'][$moduleId]??[];$settings['modules'][$moduleId]=array_replace_recursive(is_array($current)?$current:[],$normalized);}
  loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId,'presetId'=>$presetId]+settings_payload($project));
}

if($action==='orb-save'){
  $moduleId=safe_token((string)($body['moduleId']??'core.ui.orb-dock'));$incoming=$body['targets']??[];if(!is_array($incoming))json_out(['ok'=>false,'error'=>'invalid-orb-targets'],400);
  $mods=scan_admin_modules($project);$valid=[];foreach($mods as $m){if(($m['presentation']['role']??'')==='content'&&$m['actionId']!==$moduleId)$valid[$m['actionId']]=$m;}
  $normalized=[];foreach($incoming as $id=>$cfg){$id=safe_token((string)$id);if($id===''||!isset($valid[$id])||!is_array($cfg))continue;$meta=$valid[$id]['presentation']['orb']??[];$required=(bool)($meta['required']??false);$emoji=trim((string)($cfg['emoji']??''));if(function_exists('mb_substr'))$emoji=mb_substr($emoji,0,8,'UTF-8');else $emoji=substr($emoji,0,24);$normalized[$id]=['captured'=>$required?true:(bool)($cfg['captured']??false),'emoji'=>$emoji];}
  $settings=loom_read_admin_settings($project);$current=$settings['modules'][$moduleId]??[];$current=is_array($current)?$current:[];$current['orbTargets']=$normalized;$settings['modules'][$moduleId]=$current;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId,'saved'=>$normalized]+settings_payload($project));
}

if($action==='settings'){
  json_out(['ok'=>true,'privilege'=>'Admin']+settings_payload($project));
}
if($action==='save'){
  $moduleId=safe_token((string)($body['moduleId']??''));$incoming=$body['values']??[];
  if($moduleId===''||!is_array($incoming))json_out(['ok'=>false,'error'=>'invalid-settings'],400);
  $target=null;foreach(scan_admin_modules($project) as $m)if($m['actionId']===$moduleId){$target=$m;break;}
  if(!$target)json_out(['ok'=>false,'error'=>'module-not-found'],404);
  $fields=$target['admin_settings']['fields']??[];$normalized=[];
  try{
    foreach($fields as $field){
      if(!is_array($field))continue;$id=(string)($field['id']??'');$key=(string)($field['configKey']??$id);
      if($id===''||$key===''||!array_key_exists($id,$incoming))continue;
      $normalized[$key]=normalize_admin_value($field,$incoming[$id]);
    }
  }catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  $settings=loom_read_admin_settings($project);$current=$settings['modules'][$moduleId]??[];$settings['modules'][$moduleId]=array_replace_recursive(is_array($current)?$current:[],$normalized);loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId,'saved'=>$normalized]+settings_payload($project));
}
if($action==='reset'){
  $moduleId=safe_token((string)($body['moduleId']??''));$settings=loom_read_admin_settings($project);
  if(isset($settings['modules'][$moduleId]))unset($settings['modules'][$moduleId]);
  loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'moduleId'=>$moduleId]+settings_payload($project));
}
json_out(['ok'=>false,'error'=>'unknown-action'],400);
