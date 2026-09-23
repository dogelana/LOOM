<?php
// @loom-file release=0.15.04 revision=12 policy=package-priority
require __DIR__.'/_common.php';
$requestClientId=safe_token((string)($_GET['clientId']??''));if($requestClientId!=='')loom_capture_request_ip($requestClientId,'');

$projects=[];

function loom_home_project_branding(string $slug,string $dir): array {
  $logoConfig=[];$textConfig=[];
  $projectMeta=loom_project_effective_data($slug);
  $projectBrand=is_array($projectMeta['branding']??null)?$projectMeta['branding']:[];
  $actionRoot=$dir.'/actions';
  if(is_dir($actionRoot)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($actionRoot,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
      if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
      $manifest=read_json_file($file->getPathname());if(!$manifest||($manifest['enabled']??true)===false)continue;
      $id=(string)($manifest['action']['id']??'');
      if($id==='core.ui.load-logo')$logoConfig=loom_module_config_with_admin_overrides($slug,$manifest);
      if($id==='core.ui.load-logo-text')$textConfig=loom_module_config_with_admin_overrides($slug,$manifest);
    }
  }

  $logoUrl=null;
  // Project metadata owns canonical project branding. A visible Logo module may
  // override which asset is used when installed, but removing that module does
  // not erase the project's identity.
  $assetPath=(string)($projectBrand['logo_asset']??($logoConfig['assetPath']??'assets/logo.png'));
  $logoUrl=loom_project_asset_url($slug,$assetPath);

  $hex=function($value,$fallback){$v=strtoupper(trim((string)$value));return preg_match('/^#[0-9A-F]{6}$/',$v)?$v:$fallback;};
  $font=preg_replace('/[<>;{}]/','',trim((string)($textConfig['fontFamily']??'system-ui')))?:'system-ui';
  $fontCss=trim((string)($textConfig['fontGoogleCss']??''));
  if($fontCss!==''&&!preg_match('#^https://fonts\\.googleapis\\.com/#i',$fontCss))$fontCss='';

  return [
    'logo_url'=>$logoUrl,
    'logo_alt'=>(string)($projectBrand['logo_alt']??($logoConfig['alt']??($slug.' logo'))),
    // Home deliberately ignores project logo scale and logo-text fontSize.
    // The card presentation size is a LOOM Home standard.
    'wordmark'=>[
      'line1'=>substr((string)($textConfig['line1']??''),0,40),
      'line2'=>substr((string)($textConfig['line2']??''),0,40),
      'font_family'=>$font,
      'font_weight'=>max(100,min(950,(int)($textConfig['fontWeight']??900))),
      'color1'=>$hex($textConfig['greenColor']??null,'#279E38'),
      'color2'=>$hex($textConfig['beanColor']??null,'#A9DF4F'),
      'font_css'=>$fontCss
    ]
  ];
}

foreach(loom_all_project_slugs() as $slug){
  $dir=project_dir($slug);if(!$dir)continue;$baseFile=loom_project_base_file($slug);if(!$baseFile)continue;
  $data=loom_project_effective_data($slug);if(!$data)continue;
  if(!loom_request_is_admin()&&$requestClientId!==''&&!loom_project_access_status($slug,$requestClientId)['allowed'])continue;
  $socialColor=(string)($data['social_color']??'#000000');if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$socialColor))$socialColor='#000000';$socialColor=strtoupper($socialColor);
  $projects[]=['slug'=>$slug,'name'=>$data['name']??$slug,'tagline'=>$data['tagline']??'','description'=>$data['description']??'','bio'=>$data['bio']??'','social_color'=>$socialColor,'theme'=>$data['theme']??'default','version'=>$data['version']??'0.0.0','source'=>loom_project_source($slug),'branding'=>loom_home_project_branding($slug,$dir),'app_url'=>loom_project_app_url($slug),'pegboard_url'=>"pegboard/?project=$slug&v=".rawurlencode(loom_release_version()),'registry_url'=>"registry/?project=$slug&v=".rawurlencode(loom_release_version())];
}
usort($projects,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$isAdmin=function_exists('loom_request_is_admin')&&loom_request_is_admin();
json_out(['engine'=>'LOOM','engine_version'=>loom_release_version(),'projects'=>$projects,'archived_projects'=>$isAdmin?list_archived_projects():[],'admin'=>$isAdmin]);
