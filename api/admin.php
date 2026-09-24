<?php
// @loom-file release=0.15.36 revision=16 policy=package-priority
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';

function scan_admin_modules(string $project): array {
  $dir=project_dir($project); if(!$dir)return [];
  $out=loom_scan_project_core_modules($project);$coreIds=[];foreach($out as $cm)$coreIds[$cm['actionId']]=true;$root=$dir.'/actions';
  if(is_dir($root)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
      if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
      $m=read_json_file($file->getPathname());if(!$m)continue;
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
        'source'=>'project','scope'=>'project','version'=>(string)($m['module']['version']??'0.0.0'),'manifestEnabled'=>(bool)($m['enabled']??true),'manifestHideOnMobile'=>(bool)($m['presentation']['responsive']['hideOnMobile']??false)
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
    $m=read_json_file($file->getPathname());if(!$m)continue;
    $actionId=(string)($m['action']['id']??'');
    if($actionId===''||!loom_project_module_enabled($project,$actionId,(bool)($m['enabled']??true)))continue;
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
  foreach(loom_scan_global_core_modules() as $g){$id=$g['actionId'];$catalog[]=['actionId'=>$id,'name'=>$g['name'],'description'=>$g['description'],'scope'=>'global','source'=>'loom-core','version'=>'core','enabled'=>loom_global_module_enabled($id,(bool)($g['manifestEnabled']??true)),'locked'=>false,'mobileVisibilitySupported'=>false,'hideOnMobile'=>false];}
  foreach($mods as $m){$id=$m['actionId'];$defaultHide=(bool)($m['manifestHideOnMobile']??false);$catalog[]=['actionId'=>$id,'name'=>$m['name'],'description'=>$m['description'],'scope'=>'project','source'=>$m['source']??'project','version'=>(string)($m['version']??'module'),'enabled'=>loom_project_module_enabled($project,$id,(bool)($m['manifestEnabled']??true)),'locked'=>false,'mobileVisibilitySupported'=>true,'hideOnMobile'=>loom_project_module_hide_on_mobile($project,$id,$defaultHide),'hideOnMobileDefault'=>$defaultHide,'chrome'=>loom_project_module_presentation_policy($project,$id,is_array($m['presentation']??null)?$m['presentation']:[])];}
  foreach(loom_html_framer_public_frames($project) as $f){$id='html.frame.'.(string)$f['id'];$catalog[]=['actionId'=>$id,'name'=>(string)($f['title']??'HTML Frame'),'description'=>'HTML Framer package: '.(string)($f['zipName']??''),'scope'=>'project','source'=>'html-framer','version'=>'frame-r'.(string)($f['revision']??1),'enabled'=>loom_project_module_enabled($project,$id,(bool)($f['enabled']??true)),'locked'=>false,'mobileVisibilitySupported'=>true,'hideOnMobile'=>loom_project_module_hide_on_mobile($project,$id,false),'hideOnMobileDefault'=>false,'chrome'=>loom_project_module_presentation_policy($project,$id,['role'=>'content','collapsible'=>true])];}
  usort($catalog,fn($a,$b)=>strcmp($a['scope'],$b['scope'])?:strcmp($a['source'],$b['source'])?:strcasecmp($a['name'],$b['name']));
  return ['project'=>$project,'project_profile'=>loom_project_profile_payload($project),'project_source'=>loom_project_source($project),'project_presentation'=>loom_project_presentation_effective($project),'modules'=>$mods,'module_catalog'=>$catalog,'extensions'=>$extensions,'updatedAt'=>$saved['updatedAt']??null];
}

$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
  $clientId=safe_token((string)($_GET['clientId']??''));
  $status=loom_bootstrap_or_privilege($clientId,(($_GET['claim']??'0')==='1'));
  if(!empty($status['isAdmin']))loom_issue_admin_navigation_cookie($clientId);
  json_out(['ok'=>true]+$status);
}
if($method!=='POST')json_out(['ok'=>false,'error'=>'GET or POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$action=(string)($body['action']??'status');$clientId=safe_token((string)($body['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,(string)($body['project']??''));
if($action==='status'){
  $status=loom_bootstrap_or_privilege($clientId,(bool)($body['claim']??false));
  $statusProject=safe_slug((string)($body['project']??''));
  // Keep the universal shell status path intentionally lightweight. Do not
  // enumerate users/guests or full Access Manager state on every page load.
  $isOwner=loom_access_is_system_owner($clientId);
  $projectRole=$statusProject!==''?loom_access_project_role($clientId,$statusProject):($isOwner?'system-owner':(loom_access_client_is_loom_admin($clientId)?'loom-admin':'member'));
  $caps=loom_access_effective_capabilities($clientId,$statusProject);
  if(!empty($status['isAdmin'])||$isOwner||$projectRole==='loom-admin')loom_issue_admin_navigation_cookie($clientId);
  json_out(['ok'=>true]+$status+['projectRole'=>$projectRole,'capabilities'=>$caps,'isSystemOwner'=>$isOwner]);
}

// v0.15.18: authorization is capability-based. Global actions still require a
// LOOM Administrator, while project actions may be delegated to project admins/managers.
$accessProject=safe_slug((string)($body['project']??''));
$projectActionCaps=[
  'settings'=>'project.view','save'=>'project.modules','reset'=>'project.modules',
  'module-mobile-visibility'=>'project.modules','module-presentation-save'=>'project.modules','module-presentation-override'=>'project.modules','project-profile-save'=>'project.settings',
  'project-logo-upload'=>'project.settings','showcase-bio-save'=>'project.content',
  'showcase-image-upload'=>'project.content','showcase-image-remove'=>'project.content',
  'favicon-upload'=>'project.settings','favicon-use-logo'=>'project.settings',
  'apply-preset'=>'project.modules','orb-save'=>'project.modules'
];
if($action==='module-toggle'&&(($body['scope']??'project')!=='global'))$projectActionCaps['module-toggle']='project.modules';
if(isset($projectActionCaps[$action])){
  if($accessProject===''||!project_dir($accessProject))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  loom_require_project_capability($clientId,$accessProject,$projectActionCaps[$action]);
}else loom_require_admin($clientId);

if($action==='global-settings'){
  json_out(['ok'=>true,'privilege'=>'Admin']+loom_global_settings_payload());
}
if($action==='domain-landing-save'){
  $mode=(string)($body['mode']??'loom-home');$project=safe_slug((string)($body['project']??''));
  try{loom_write_domain_routing($mode,$project);}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Domain Landing updated']+loom_global_settings_payload());
}
if($action==='domain-landing-home'){
  try{loom_write_domain_routing('loom-home','');}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],500);}
  json_out(['ok'=>true,'message'=>'LOOM Home restored to the installation base URL']+loom_global_settings_payload());
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
if($action==='module-mobile-visibility'){
  $scope=(string)($body['scope']??'project');$moduleId=(string)($body['moduleId']??'');$hide=(bool)($body['hideOnMobile']??false);
  if($scope!=='project')json_out(['ok'=>false,'error'=>'mobile-visibility-is-project-scoped'],400);
  if($moduleId==='')json_out(['ok'=>false,'error'=>'module-id-required'],400);
  $toggleProject=safe_slug((string)($body['project']??''));if($toggleProject===''||!project_dir($toggleProject))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  $known=false;foreach(scan_admin_modules($toggleProject) as $m)if($m['actionId']===$moduleId){$known=true;break;}if(!$known&&str_starts_with($moduleId,'html.frame.'))foreach(loom_html_framer_public_frames($toggleProject) as $f)if($moduleId==='html.frame.'.($f['id']??'')){$known=true;break;}
  if(!$known)json_out(['ok'=>false,'error'=>'project-module-not-found'],404);
  loom_set_project_module_hide_on_mobile($toggleProject,$moduleId,$hide);json_out(['ok'=>true,'scope'=>'project','moduleId'=>$moduleId,'hideOnMobile'=>$hide]+settings_payload($toggleProject));
}
if($action==='module-presentation-save'){
  $toggleProject=safe_slug((string)($body['project']??''));
  try{loom_set_project_presentation($toggleProject,is_array($body['presentation']??null)?$body['presentation']:[]);}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Project module presentation updated']+settings_payload($toggleProject));
}
if($action==='module-presentation-override'){
  $toggleProject=safe_slug((string)($body['project']??''));$moduleId=(string)($body['moduleId']??'');if($moduleId==='')json_out(['ok'=>false,'error'=>'module-id-required'],400);
  try{loom_set_project_module_presentation($toggleProject,$moduleId,is_array($body['presentation']??null)?$body['presentation']:[]);}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Module presentation override updated']+settings_payload($toggleProject));
}

$project=safe_slug((string)($body['project']??''));
if(!$project||!project_dir($project))json_out(['ok'=>false,'error'=>'project-not-found'],404);


if($action==='project-profile-save'){
  $incoming=$body['profile']??[];if(!is_array($incoming))json_out(['ok'=>false,'error'=>'invalid-project-profile'],400);
  try{$profile=loom_write_project_profile($project,$incoming);}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Project profile saved','profile'=>$profile,'project_profile'=>$profile]);
}
if($action==='project-logo-upload'){
  try{$profile=loom_save_project_logo($project,(string)($body['pngBase64']??''));}catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  json_out(['ok'=>true,'message'=>'Project logo saved in persistent instance overlay','profile'=>$profile,'project_profile'=>$profile]);
}


if($action==='showcase-bio-save'){
  $moduleId='loom.showcase';$mode=(string)($body['bioMode']??'project');if(!in_array($mode,['project','custom'],true))json_out(['ok'=>false,'error'=>'invalid-showcase-bio-mode'],400);
  $bio=loom_clean_project_text((string)($body['bioOverride']??''),1800);
  $settings=loom_read_admin_settings($project);$cur=$settings['modules'][$moduleId]??[];if(!is_array($cur))$cur=[];$cur['bioMode']=$mode;if($mode==='custom')$cur['bioOverride']=$bio;$settings['modules'][$moduleId]=$cur;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'message'=>$mode==='project'?'Showcase now follows the project bio':'Custom Showcase bio saved']+settings_payload($project));
}
if($action==='showcase-image-upload'){
  $moduleId='loom.showcase';
  try{$png=loom_decode_png_payload((string)($body['pngBase64']??''));$target=loom_project_asset_target($project,'assets/showcase.png');if(@file_put_contents($target,$png,LOCK_EX)===false)throw new RuntimeException('Could not write Showcase image');}
  catch(RuntimeException $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
  $settings=loom_read_admin_settings($project);$cur=$settings['modules'][$moduleId]??[];if(!is_array($cur))$cur=[];$cur['hasImage']=true;$cur['imageAsset']='assets/showcase.png';$cur['imageVersion']=file_cache_version($target);$settings['modules'][$moduleId]=$cur;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'message'=>'Showcase image saved in persistent project overlay']+settings_payload($project));
}
if($action==='showcase-image-remove'){
  $moduleId='loom.showcase';$target=loom_project_overlay_asset($project,'assets/showcase.png');if($target&&is_file($target)&&!@unlink($target))json_out(['ok'=>false,'error'=>'could-not-remove-showcase-image'],500);
  $settings=loom_read_admin_settings($project);$cur=$settings['modules'][$moduleId]??[];if(!is_array($cur))$cur=[];$cur['hasImage']=false;$cur['imageVersion']=server_timestamp();$settings['modules'][$moduleId]=$cur;loom_write_admin_settings($project,$settings);
  json_out(['ok'=>true,'message'=>'Showcase image removed']+settings_payload($project));
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
